<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\AtendimentoFactory;
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

class Atendimento extends Model
{
    /** @use HasFactory<AtendimentoFactory> */
    use HasFactory;

    protected $table = 'atendimentos';

    protected $hidden = ['motivo_divergencia'];

    /** @param array<string, mixed> $entrada */
    public static function registrar(int $agendamentoId, User $autor, array $entrada): EventoAtendimento
    {
        Gate::forUser($autor)->authorize(Permissao::REGISTRAR_RELATORIOS_VISITA);
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $dados = Validator::make($entrada, [
            'operacao_id' => ['required', 'uuid'], 'versao' => ['required', 'integer', 'min:1'],
            'inicio_efetivo_em' => ['required', 'date_format:Y-m-d\TH:i'],
            'fim_efetivo_em' => ['required', 'date_format:Y-m-d\TH:i', 'after:inicio_efetivo_em'],
            'responsavel_user_id' => ['required', 'integer', 'min:1'],
            'modalidade' => ['required', 'in:domicilio,banco'], 'local_atendimento' => ['required', 'string', 'max:500'],
            'motivo_divergencia' => ['nullable', 'string', 'max:2000'], 'realizacao_confirmada' => ['required', 'accepted'],
        ])->validate();
        $dados['versao'] = (int) $dados['versao'];
        $dados['responsavel_user_id'] = (int) $dados['responsavel_user_id'];
        $dados['realizacao_confirmada'] = true;
        $dados['motivo_divergencia'] = $dados['motivo_divergencia'] ?? null;
        $hash = hash('sha256', json_encode(['realizacao', $agendamentoId, $autor->id, $dados], JSON_THROW_ON_ERROR));

        try {
            return DB::transaction(function () use ($agendamentoId, $autor, $dados, $hash): EventoAtendimento {
                $referencia = Agendamento::query()->findOrFail($agendamentoId);
                $usuarios = User::query()->whereIn('id', [$referencia->responsavel_user_id, $dados['responsavel_user_id']])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                $pedido = Solicitacao::query()->lockForUpdate()->findOrFail($referencia->solicitacao_id);
                $agenda = Agendamento::query()->lockForUpdate()->findOrFail($agendamentoId);
                Gate::forUser($autor)->authorize(Permissao::REGISTRAR_RELATORIOS_VISITA);
                $anterior = EventoAtendimento::query()->where('operacao_id', $dados['operacao_id'])->lockForUpdate()->first();
                if ($anterior) {
                    abort_unless(hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados.');

                    return $anterior;
                }
                $reserva = ReservaAgenda::query()->whereKey($pedido->id)->lockForUpdate()->first();
                abort_unless($pedido->status === 'admitida' && $pedido->interessada_id !== null
                    && $agenda->status === 'confirmado' && $agenda->versao === $dados['versao']
                    && $agenda->responsavel_user_id === $referencia->responsavel_user_id
                    && $reserva?->agendamento_id === $agenda->id
                    && ! $pedido->agendamentos()->whereHas('atendimento')->exists(), 409, 'O acompanhamento mudou ou já foi atendido. Recarregue antes de continuar.');
                $responsavel = $usuarios->get($dados['responsavel_user_id']);
                if ($responsavel === null || (! $responsavel->isAdmin() && ! $responsavel->isFuncionario())) {
                    throw ValidationException::withMessages(['responsavel_user_id' => 'Selecione um responsável com conta interna.']);
                }
                $inicio = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $dados['inicio_efetivo_em'], 'America/Sao_Paulo')->utc();
                $fim = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $dados['fim_efetivo_em'], 'America/Sao_Paulo')->utc();
                if ($inicio->lt($agenda->confirmado_em) || $fim->gt(now())) {
                    throw ValidationException::withMessages(['inicio_efetivo_em' => 'Informe um período após a confirmação e sem datas futuras.']);
                }
                $divergente = $responsavel->id !== $agenda->responsavel_user_id || $dados['modalidade'] !== $agenda->modalidade
                    || $dados['local_atendimento'] !== $agenda->local_atendimento;
                if ($divergente && trim($dados['motivo_divergencia'] ?? '') === '') {
                    throw ValidationException::withMessages(['motivo_divergencia' => 'Justifique a alteração de responsável ou local.']);
                }
                $atendimento = new static;
                $atendimento->forceFill(['agendamento_id' => $agenda->id, 'inicio_efetivo_em' => $inicio, 'fim_efetivo_em' => $fim,
                    'responsavel_user_id' => $responsavel->id, 'modalidade' => $dados['modalidade'], 'local_atendimento' => $dados['local_atendimento'],
                    'motivo_divergencia' => $dados['motivo_divergencia'], 'registrado_por_user_id' => $autor->id, 'registrado_em' => now()->utc()])->save();
                $relatorio = new RelatorioVisita;
                $relatorio->forceFill(['atendimento_id' => $atendimento->id, 'estado' => 'rascunho', 'numero_versao_vigente' => 0,
                    'revisao' => 1, 'criado_por_user_id' => $autor->id, 'ultimo_editor_user_id' => $autor->id])->save();
                $agenda->status = 'realizado';
                $agenda->versao++;
                $agenda->save();
                $reserva->delete();
                $pedido->versao++;
                $pedido->save();
                $operacional = new EventoAgendamento;
                $operacional->forceFill(['agendamento_id' => $agenda->id, 'autor_user_id' => $autor->id, 'acao' => 'realizacao',
                    'operacao_id' => $dados['operacao_id'], 'conteudo_hash' => $hash, 'versao_analisada' => $dados['versao'],
                    'motivo' => 'Realização da visita registrada.', 'dados_anteriores' => ['status' => 'confirmado', 'versao' => $dados['versao']],
                    'dados_novos' => ['status' => 'realizado', 'versao' => $agenda->versao, 'atendimento_id' => $atendimento->id],
                    'ocorrido_em' => now()->utc()])->save();
                $evento = new EventoAtendimento;
                $evento->forceFill(['atendimento_id' => $atendimento->id, 'relatorio_visita_id' => $relatorio->id,
                    'autor_user_id' => $autor->id, 'acao' => 'realizacao', 'operacao_id' => $dados['operacao_id'],
                    'conteudo_hash' => $hash, 'revisao_analisada' => $dados['versao'], 'ocorrido_em' => now()->utc()])->save();

                return $evento;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! EventoAtendimento::query()->where('operacao_id', $dados['operacao_id'])->exists()
                && ! EventoAgendamento::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        }
    }

    protected function casts(): array
    {
        return ['inicio_efetivo_em' => 'immutable_datetime', 'fim_efetivo_em' => 'immutable_datetime', 'registrado_em' => 'immutable_datetime'];
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function relatorio(): HasOne
    {
        return $this->hasOne(RelatorioVisita::class, 'atendimento_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAtendimento::class, 'atendimento_id');
    }

    public function avaliacao(): HasOne
    {
        return $this->hasOne(Avaliacao::class, 'atendimento_id');
    }
}
