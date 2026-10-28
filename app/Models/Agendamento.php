<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AgendamentoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class Agendamento extends Model
{
    /** @use HasFactory<AgendamentoFactory> */
    use HasFactory;

    protected $table = 'agendamentos';

    /** @param array<string, mixed> $dados */
    public static function confirmar(int $solicitacaoId, User $autor, array $dados): EventoAgendamento
    {
        return self::executar('confirmacao', $solicitacaoId, $autor, $dados);
    }

    /** @param array<string, mixed> $dados */
    public function reagendar(User $autor, array $dados): EventoAgendamento
    {
        return self::executar('reagendamento', $this->id, $autor, $dados);
    }

    /** @param array<string, mixed> $dados */
    public function cancelar(User $autor, array $dados): EventoAgendamento
    {
        return self::executar('cancelamento', $this->id, $autor, $dados);
    }

    /** @param array<string, mixed> $dados */
    public function registrarNaoRealizacao(User $autor, array $dados): EventoAgendamento
    {
        return self::executar('nao_realizacao', $this->id, $autor, $dados);
    }

    /** @param array<string, mixed> $entrada */
    private static function executar(string $acao, int $origem, User $autor, array $entrada): EventoAgendamento
    {
        Gate::forUser($autor)->authorize(Permissao::GERENCIAR_AGENDA);
        $cria = in_array($acao, ['confirmacao', 'reagendamento'], true);
        $regras = [
            'operacao_id' => ['required', 'uuid'], 'versao' => ['required', 'integer', 'min:1'],
            'motivo' => ['required', 'string', 'max:2000'],
        ];
        if ($cria) {
            $regras += [
                'contato_id' => ['required', 'integer', 'min:1'], 'responsavel_user_id' => ['required', 'integer', 'min:1'],
                'modalidade' => ['required', 'in:domicilio,banco'], 'local_atendimento' => ['required', 'string', 'max:500'],
                'inicio_previsto_em' => ['required', 'date_format:Y-m-d\TH:i'],
                'fim_previsto_em' => ['required', 'date_format:Y-m-d\TH:i', 'after:inicio_previsto_em'],
                'combinado_confirmado' => ['required', 'accepted'],
            ];
        }
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $dados = Validator::make($entrada, $regras)->validate();
        $dados['versao'] = (int) $dados['versao'];
        if ($cria) {
            $dados['contato_id'] = (int) $dados['contato_id'];
            $dados['responsavel_user_id'] = (int) $dados['responsavel_user_id'];
            $dados['combinado_confirmado'] = true;
        }
        $hash = hash('sha256', json_encode([$acao, $origem, $autor->id, $dados], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($acao, $origem, $autor, $dados, $hash, $cria): EventoAgendamento {
                $referencia = $acao === 'confirmacao' ? null : self::query()->findOrFail($origem);
                $responsaveis = array_filter([$referencia?->responsavel_user_id, $dados['responsavel_user_id'] ?? null]);
                $usuarios = User::query()->whereIn('id', $responsaveis)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $pedido = Solicitacao::query()->lockForUpdate()->findOrFail($referencia?->solicitacao_id ?? $origem);
                $atual = $referencia ? self::query()->lockForUpdate()->findOrFail($origem) : null;
                Gate::forUser($autor)->authorize(Permissao::GERENCIAR_AGENDA);
                $anterior = EventoAgendamento::query()->where('operacao_id', $dados['operacao_id'])->lockForUpdate()->first();
                if ($anterior) {
                    abort_unless(hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados.');

                    return $anterior;
                }
                abort_unless($pedido->status === 'admitida' && $pedido->interessada_id !== null, 409, 'O pedido precisa estar admitido e vinculado à interessada.');
                $reserva = ReservaAgenda::query()->whereKey($pedido->id)->lockForUpdate()->first();
                if ($atual) {
                    abort_unless($atual->status === 'confirmado' && $atual->versao === $dados['versao']
                        && $reserva?->agendamento_id === $atual->id
                        && $atual->responsavel_user_id === $referencia->responsavel_user_id, 409, 'O compromisso mudou. Recarregue antes de continuar.');
                } else {
                    abort_unless($pedido->versao === $dados['versao'] && $reserva === null, 409, 'O acompanhamento mudou ou já possui uma reserva. Recarregue a página.');
                }
                $antes = $atual?->snapshotAgenda();
                $novo = null;
                if ($cria) {
                    $responsavel = $usuarios->get($dados['responsavel_user_id']);
                    self::exigir($responsavel !== null && ($responsavel->isAdmin() || $responsavel->isFuncionario()), 'responsavel_user_id', 'Selecione um responsável com conta interna.');
                    $contato = ContatoSolicitacao::query()->lockForUpdate()->findOrFail($dados['contato_id']);
                    self::exigir($contato->contexto === 'acompanhamento' && $contato->resultado === 'realizado'
                        && $contato->solicitacoes()->whereKey($pedido->id)->exists(), 'contato_id', 'Selecione um contato realizado deste acompanhamento que confirme a combinação.');
                    if ($atual) {
                        self::exigir($contato->id !== $atual->contato_confirmacao_id && $contato->ocorrido_em->gte($atual->confirmado_em->startOfMinute()),
                            'contato_id', 'Registre um novo contato após a confirmação anterior para combinar a alteração.');
                    }
                    $inicio = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $dados['inicio_previsto_em'], 'America/Sao_Paulo')->utc();
                    $fim = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $dados['fim_previsto_em'], 'America/Sao_Paulo')->utc();
                    self::exigir($inicio->isFuture(), 'inicio_previsto_em', 'O início precisa estar no futuro, no horário de Marília.');
                    // A trava do responsável serializa agendas de pedidos diferentes, inclusive quando ainda não há reserva.
                    $conflito = self::query()->where('responsavel_user_id', $responsavel->id)->where('status', 'confirmado')
                        ->when($atual, fn (Builder $query): Builder => $query->whereKeyNot($atual->id))
                        ->where('inicio_previsto_em', '<', $fim)->where('fim_previsto_em', '>', $inicio)
                        ->lockForUpdate()->first();
                    self::exigir($conflito === null, 'inicio_previsto_em', 'O funcionário já tem um compromisso marcado para esse horário. Escolha outro horário ou responsável.');
                    $novo = new self;
                    $novo->forceFill([
                        'solicitacao_id' => $pedido->id, 'contato_confirmacao_id' => $contato->id,
                        'responsavel_user_id' => $responsavel->id, 'confirmado_por_user_id' => $autor->id,
                        'modalidade' => $dados['modalidade'], 'local_atendimento' => $dados['local_atendimento'],
                        'inicio_previsto_em' => $inicio, 'fim_previsto_em' => $fim, 'confirmado_em' => now()->utc(),
                        'status' => 'confirmado', 'substitui_agendamento_id' => $atual?->id, 'versao' => 1,
                    ])->save();
                } elseif ($acao === 'nao_realizacao') {
                    self::exigir($atual->inicio_previsto_em->lte(now()), 'versao', 'A não realização só pode ser registrada após o horário combinado.');
                }
                if ($atual) {
                    $atual->status = match ($acao) {
                        'reagendamento' => 'substituido', 'cancelamento' => 'cancelado', default => 'nao_realizado',
                    };
                    $atual->versao++;
                    $atual->save();
                    $reserva->delete();
                }
                if ($novo) {
                    $novaReserva = new ReservaAgenda;
                    $novaReserva->forceFill(['solicitacao_id' => $pedido->id, 'agendamento_id' => $novo->id])->save();
                }
                $pedido->versao++;
                $pedido->save();
                $evento = new EventoAgendamento;
                $evento->forceFill([
                    'agendamento_id' => $atual?->id ?? $novo->id,
                    'agendamento_resultante_id' => $atual && $novo ? $novo->id : null,
                    'autor_user_id' => $autor->id, 'acao' => $acao,
                    'operacao_id' => $dados['operacao_id'], 'conteudo_hash' => $hash,
                    'versao_analisada' => $atual ? $dados['versao'] : null,
                    'motivo' => $dados['motivo'], 'dados_anteriores' => $antes,
                    'dados_novos' => ['agendamento' => ($atual ?? $novo)->snapshotAgenda(),
                        'resultante' => $atual && $novo ? $novo->snapshotAgenda() : null,
                        'combinado_confirmado' => $cria, 'fuso_entrada' => 'America/Sao_Paulo'],
                    'ocorrido_em' => now()->utc(),
                ])->save();

                return $evento;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! EventoAgendamento::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        }
    }

    /** @return array<string, mixed> */
    private function snapshotAgenda(): array
    {
        return $this->only(['id', 'solicitacao_id', 'contato_confirmacao_id', 'responsavel_user_id', 'modalidade',
            'local_atendimento', 'status', 'versao']) + [
                'inicio_previsto_em' => $this->inicio_previsto_em->utc()->format('Y-m-d H:i:s'),
                'fim_previsto_em' => $this->fim_previsto_em->utc()->format('Y-m-d H:i:s'),
            ];
    }

    private static function exigir(bool $condicao, string $campo, string $mensagem): void
    {
        if (! $condicao) {
            throw ValidationException::withMessages([$campo => $mensagem]);
        }
    }

    protected function casts(): array
    {
        return ['inicio_previsto_em' => 'immutable_datetime', 'fim_previsto_em' => 'immutable_datetime',
            'confirmado_em' => 'immutable_datetime', 'versao' => 'integer'];
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    public function contatoConfirmacao(): BelongsTo
    {
        return $this->belongsTo(ContatoSolicitacao::class, 'contato_confirmacao_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function confirmadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmado_por_user_id');
    }

    public function substitui(): BelongsTo
    {
        return $this->belongsTo(self::class, 'substitui_agendamento_id');
    }

    public function sucessor(): HasOne
    {
        return $this->hasOne(self::class, 'substitui_agendamento_id');
    }

    public function reserva(): HasOne
    {
        return $this->hasOne(ReservaAgenda::class, 'agendamento_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAgendamento::class, 'agendamento_id');
    }

    public function atendimento(): HasOne
    {
        return $this->hasOne(Atendimento::class, 'agendamento_id');
    }
}
