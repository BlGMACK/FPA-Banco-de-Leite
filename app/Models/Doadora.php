<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\DoadoraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Habilitação para doar, criada pela decisão de habilitação; os dados pessoais ficam na interessada, sem cópia (D9). */
class Doadora extends Model
{
    /** @use HasFactory<DoadoraFactory> */
    use HasFactory;

    public const ESTADOS = ['ativa' => 'Ativa', 'suspensa' => 'Suspensa', 'encerrada' => 'Encerrada', 'revogada' => 'Revogada'];

    public const TIPOS = ['definitiva' => 'Definitiva', 'temporaria' => 'Temporária'];

    /** Estados em que a habilitação ainda vale ou pode voltar a valer; só um por interessada. */
    public const ESTADOS_VIGENTES = ['ativa', 'suspensa'];

    /** Ações que terminam a habilitação e exigem confirmação. */
    public const ACOES_FINAIS = ['encerramento', 'revogacao'];

    protected $table = 'doadoras';

    public function vigente(): bool
    {
        return in_array($this->estado, self::ESTADOS_VIGENTES, true);
    }

    /** Temporária vigente com data-limite anterior a hoje; só sinaliza, sem encerrar sozinha. */
    public function vencida(): bool
    {
        return $this->tipo === 'temporaria' && $this->vigente() && $this->valida_ate !== null
            && $this->valida_ate->toDateString() < ItemAvaliacao::hoje();
    }

    /** @param array<string, mixed> $entrada */
    public function registrar(User $autor, array $entrada): EventoDoadora
    {
        Gate::forUser($autor)->authorize(Permissao::AVALIAR_DOADORAS);
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $confirmada = ($entrada['confirmacao_definitiva'] ?? null) === '1';
        unset($entrada['confirmacao_definitiva']);
        $dados = Validator::make($entrada, [
            'operacao_id' => ['required', 'uuid'], 'versao' => ['required', 'integer', 'min:1'],
            'acao' => ['required', 'in:'.implode(',', array_keys(EventoDoadora::ACOES))],
            'motivo' => ['required', 'string', 'max:2000'], 'condicao' => ['nullable', 'string', 'max:2000'],
            'valida_ate' => ['nullable', 'date_format:Y-m-d'],
        ], ['acao.required' => 'Escolha a ação.', 'acao.in' => 'Ação inválida.', 'motivo.required' => 'Informe o motivo.',
            'valida_ate.date_format' => 'Informe uma data válida.'])->validate();
        $dados['versao'] = (int) $dados['versao'];
        $renovacao = $dados['acao'] === 'renovacao';
        $dados['condicao'] = $renovacao && ($dados['condicao'] ?? '') !== '' ? $dados['condicao'] : null;
        $dados['valida_ate'] = $renovacao && ($dados['valida_ate'] ?? '') !== '' ? $dados['valida_ate'] : null;
        $falhas = $renovacao ? static::falhasTemporaria($dados['condicao'], $dados['valida_ate']) : [];
        if (in_array($dados['acao'], self::ACOES_FINAIS, true) && ! $confirmada) {
            $falhas['confirmacao_definitiva'] = 'Confirme que a habilitação termina e não pode ser retomada.';
        }
        if ($falhas) {
            throw ValidationException::withMessages($falhas);
        }
        $hash = hash('sha256', json_encode([$dados['acao'], $this->getKey(), $autor->id, $dados], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($autor, $dados, $hash): EventoDoadora {
                $doadora = static::query()->lockForUpdate()->findOrFail($this->getKey());
                Gate::forUser($autor)->authorize(Permissao::AVALIAR_DOADORAS);
                $anterior = EventoDoadora::query()->where('operacao_id', $dados['operacao_id'])->lockForUpdate()->first();
                if ($anterior) {
                    abort_unless(hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados.');

                    return $anterior;
                }
                abort_unless($doadora->versao === $dados['versao'], 409, 'A habilitação mudou. Recarregue antes de continuar.');
                $estadoAnterior = $doadora->estado;
                $doadora->aplicar($dados);
                $doadora->versao++;
                $doadora->save();
                $evento = new EventoDoadora;
                $evento->forceFill(['doadora_id' => $doadora->id, 'acao' => $dados['acao'], 'estado_anterior' => $estadoAnterior,
                    'estado_novo' => $doadora->estado, 'tipo' => $doadora->tipo, 'condicao' => $dados['condicao'], 'valida_ate' => $dados['valida_ate'],
                    'motivo' => $dados['motivo'], 'autor_user_id' => $autor->id, 'ocorrido_em' => now()->utc(),
                    'operacao_id' => $dados['operacao_id'], 'conteudo_hash' => $hash, 'versao_analisada' => $dados['versao']])->save();

                return $evento;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! EventoDoadora::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        }
    }

    /**
     * Condição e data-limite obrigatórias da habilitação temporária; a data precisa ser posterior a hoje.
     *
     * @return array<string, string>
     */
    public static function falhasTemporaria(?string $condicao, ?string $validaAte): array
    {
        $falhas = [];
        if ($condicao === null) {
            $falhas['condicao'] = 'Informe a condição da habilitação temporária.';
        }
        if ($validaAte === null) {
            $falhas['valida_ate'] = 'Informe a data-limite da habilitação temporária.';
        } elseif ($validaAte <= ItemAvaliacao::hoje()) {
            $falhas['valida_ate'] = 'A data-limite deve ser posterior a hoje.';
        }

        return $falhas;
    }

    /** @param array<string, mixed> $dados */
    private function aplicar(array $dados): void
    {
        abort_unless($this->vigente(), 409, 'A habilitação já terminou e não aceita novas ações.');
        switch ($dados['acao']) {
            case 'suspensao':
                abort_unless($this->estado === 'ativa', 409, 'Só é possível suspender uma habilitação ativa.');
                $this->estado = 'suspensa';
                break;
            case 'retomada':
                abort_unless($this->estado === 'suspensa', 409, 'Só é possível retomar uma habilitação suspensa.');
                $this->estado = 'ativa';
                break;
            case 'renovacao':
                abort_unless($this->tipo === 'temporaria', 409, 'Só a habilitação temporária tem prazo para renovar.');
                $this->forceFill(['condicao' => $dados['condicao'], 'valida_ate' => CarbonImmutable::createFromFormat('!Y-m-d', $dados['valida_ate'])]);
                break;
            case 'conversao_definitiva':
                abort_unless($this->tipo === 'temporaria', 409, 'A habilitação já é definitiva.');
                $this->forceFill(['tipo' => 'definitiva', 'condicao' => null, 'valida_ate' => null]);
                break;
            case 'encerramento':
                $this->estado = 'encerrada';
                break;
            case 'revogacao':
                $this->estado = 'revogada';
                break;
        }
    }

    protected function casts(): array
    {
        return ['valida_ate' => 'immutable_date', 'versao' => 'integer'];
    }

    public function interessada(): BelongsTo
    {
        return $this->belongsTo(Interessada::class, 'interessada_id');
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
    }

    public function decisao(): BelongsTo
    {
        return $this->belongsTo(DecisaoAvaliacao::class, 'decisao_avaliacao_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoDoadora::class, 'doadora_id');
    }
}
