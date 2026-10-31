<?php

namespace App\Models;

use Database\Factories\AvaliacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Avaliacao extends Model
{
    /** @use HasFactory<AvaliacaoFactory> */
    use HasFactory;

    public const ESTADOS = ['aguardando_analise' => 'Aguardando análise', 'em_aguardo' => 'Em aguardo',
        'liberada_exames' => 'Liberada para exames', 'nao_apta' => 'Não apta'];

    /** Decisões que concluem a análise e travam correções do relatório. */
    public const ESTADOS_DEFINITIVOS = ['liberada_exames', 'nao_apta'];

    /** Duração da reserva da análise; vencida, outro profissional pode assumir. */
    public const MINUTOS_ANALISE = 30;

    protected $table = 'avaliacoes';

    /** Cria a avaliação da visita quando o relatório recebe a primeira versão publicada; deve rodar na transação da finalização. */
    public static function abrirParaAtendimento(int $atendimentoId): void
    {
        if (! static::query()->where('atendimento_id', $atendimentoId)->exists()) {
            $avaliacao = new static;
            $avaliacao->forceFill(['atendimento_id' => $atendimentoId, 'estado' => 'aguardando_analise', 'versao' => 1])->save();
        }
    }

    public function concluida(): bool
    {
        return in_array($this->estado, self::ESTADOS_DEFINITIVOS, true);
    }

    /** Reserva vigente de outro profissional; uma reserva vencida não bloqueia ninguém. */
    public function reservadaPorOutro(User $user): bool
    {
        return $this->analise_por_user_id !== null && $this->analise_por_user_id !== $user->id && $this->analise_expira_em?->isFuture();
    }

    public function reservadaPara(User $user): bool
    {
        return $this->analise_por_user_id === $user->id && $this->analise_expira_em?->isFuture();
    }

    /** Reserva a análise para o autor por MINUTOS_ANALISE; repetir renova o prazo. Não altera a versão da avaliação. */
    public function iniciarAnalise(User $autor): void
    {
        Gate::forUser($autor)->authorize(Permissao::AVALIAR_DOADORAS);
        DB::transaction(function () use ($autor): void {
            $referencia = static::query()->findOrFail($this->getKey());
            $relatorio = RelatorioVisita::query()->where('atendimento_id', $referencia->atendimento_id)->lockForUpdate()->firstOrFail();
            $avaliacao = static::query()->lockForUpdate()->findOrFail($this->getKey());
            Gate::forUser($autor)->authorize(Permissao::AVALIAR_DOADORAS);
            abort_if($avaliacao->concluida(), 409, 'A análise do relatório já foi concluída.');
            abort_unless($relatorio->estado === 'finalizado', 409, 'O relatório está em correção. Decida depois que a correção for finalizada.');
            abort_if($avaliacao->reservadaPorOutro($autor), 409, $avaliacao->mensagemReserva());
            $agora = now()->utc();
            $avaliacao->forceFill(['analise_por_user_id' => $autor->id, 'analise_iniciada_em' => $agora,
                'analise_expira_em' => $agora->copy()->addMinutes(self::MINUTOS_ANALISE)])->save();
        });
    }

    /** Libera a reserva do próprio autor; sem efeito se ela já venceu ou pertence a outro profissional. */
    public function encerrarAnalise(User $autor): void
    {
        DB::transaction(function () use ($autor): void {
            $avaliacao = static::query()->lockForUpdate()->findOrFail($this->getKey());
            if ($avaliacao->analise_por_user_id === $autor->id) {
                $avaliacao->forceFill(['analise_por_user_id' => null, 'analise_iniciada_em' => null, 'analise_expira_em' => null])->save();
            }
        });
    }

    public function mensagemReserva(): string
    {
        return 'A avaliação está em análise por '.$this->analisadaPor?->name.' até '
            .$this->analise_expira_em?->setTimezone('America/Sao_Paulo')->format('H:i').'.';
    }

    /** @param array<string, mixed> $entrada */
    public function decidir(User $autor, array $entrada): DecisaoAvaliacao
    {
        Gate::forUser($autor)->authorize(Permissao::AVALIAR_DOADORAS);
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $confirmada = ($entrada['confirmacao_definitiva'] ?? null) === '1';
        unset($entrada['confirmacao_definitiva']);
        $dados = Validator::make($entrada, [
            'operacao_id' => ['required', 'uuid'], 'versao' => ['required', 'integer', 'min:1'],
            'resultado' => ['required', 'in:liberada_exames,em_aguardo,nao_apta'], 'motivo' => ['nullable', 'string', 'max:2000'],
        ], ['resultado.required' => 'Selecione o resultado da análise.', 'resultado.in' => 'Selecione um resultado válido.'])->validate();
        $dados['versao'] = (int) $dados['versao'];
        $dados['motivo'] = ($dados['motivo'] ?? '') === '' ? null : $dados['motivo'];
        $falhas = [];
        if ($dados['resultado'] !== 'liberada_exames' && $dados['motivo'] === null) {
            $falhas['motivo'] = 'Informe o motivo para deixar em aguardo ou considerar não apta.';
        }
        if (in_array($dados['resultado'], self::ESTADOS_DEFINITIVOS, true) && ! $confirmada) {
            $falhas['confirmacao_definitiva'] = 'Confirme que a decisão é definitiva e impede novas correções do relatório.';
        }
        if ($falhas) {
            throw ValidationException::withMessages($falhas);
        }
        $hash = hash('sha256', json_encode(['analise_relatorio', $this->getKey(), $autor->id, $dados], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($autor, $dados, $hash): DecisaoAvaliacao {
                $referencia = static::query()->findOrFail($this->getKey());
                $relatorio = RelatorioVisita::query()->where('atendimento_id', $referencia->atendimento_id)->lockForUpdate()->firstOrFail();
                $avaliacao = static::query()->lockForUpdate()->findOrFail($this->getKey());
                Gate::forUser($autor)->authorize(Permissao::AVALIAR_DOADORAS);
                $anterior = DecisaoAvaliacao::query()->where('operacao_id', $dados['operacao_id'])->lockForUpdate()->first();
                if ($anterior) {
                    abort_unless(hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados.');

                    return $anterior;
                }
                abort_unless($avaliacao->versao === $dados['versao'], 409, 'A avaliação mudou. Recarregue antes de continuar.');
                abort_if($avaliacao->concluida(), 409, 'A análise do relatório já foi concluída.');
                abort_unless($relatorio->estado === 'finalizado' && $relatorio->numero_versao_vigente > 0, 409,
                    'O relatório está em correção. Decida depois que a correção for finalizada.');
                abort_if($avaliacao->reservadaPorOutro($autor), 409, $avaliacao->mensagemReserva());
                $versaoRelatorio = $relatorio->versoes()->where('numero', $relatorio->numero_versao_vigente)->firstOrFail();
                $decisao = new DecisaoAvaliacao;
                $decisao->forceFill(['avaliacao_id' => $avaliacao->id, 'etapa' => 'analise_relatorio', 'resultado' => $dados['resultado'],
                    'motivo' => $dados['motivo'], 'versao_relatorio_id' => $versaoRelatorio->id, 'autor_user_id' => $autor->id,
                    'decidido_em' => now()->utc(), 'operacao_id' => $dados['operacao_id'], 'conteudo_hash' => $hash,
                    'versao_analisada' => $dados['versao']])->save();
                $avaliacao->forceFill(['estado' => $dados['resultado'], 'versao' => $avaliacao->versao + 1,
                    'analise_por_user_id' => null, 'analise_iniciada_em' => null, 'analise_expira_em' => null])->save();

                return $decisao;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! DecisaoAvaliacao::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        }
    }

    protected function casts(): array
    {
        return ['versao' => 'integer', 'analise_iniciada_em' => 'immutable_datetime', 'analise_expira_em' => 'immutable_datetime'];
    }

    public function analisadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'analise_por_user_id');
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function decisoes(): HasMany
    {
        return $this->hasMany(DecisaoAvaliacao::class, 'avaliacao_id');
    }
}
