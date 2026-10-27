<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ContatoSolicitacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ContatoSolicitacao extends Model
{
    /** @use HasFactory<ContatoSolicitacaoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'contatos_solicitacao';

    /** @param array<string, mixed> $entrada */
    public static function registrarAcompanhamento(int $solicitacaoId, User $autor, array $entrada): self
    {
        Gate::forUser($autor)->authorize(Permissao::GERENCIAR_AGENDA);
        $entrada = array_map(fn (mixed $valor): mixed => is_string($valor) ? trim($valor) : $valor, $entrada);
        $dados = Validator::make($entrada, [
            'operacao_id' => ['required', 'uuid'], 'versao' => ['required', 'integer', 'min:1'],
            'canal' => ['required', 'in:whatsapp,telefone,email'],
            'resultado' => ['required', 'in:tentado,realizado,sem_resposta,numero_incorreto'],
            'destinatario' => ['required', 'string', 'max:255'], 'finalidade' => ['required', 'string', 'max:150'],
            'resumo' => ['required', 'string', 'max:4000'], 'proxima_acao' => ['nullable', 'string', 'max:2000'],
            'ocorrido_em' => ['required', 'date_format:Y-m-d\TH:i'],
        ])->validate();
        $dados['versao'] = (int) $dados['versao'];
        $dados['proxima_acao'] = $dados['proxima_acao'] ?? null;
        $hash = hash('sha256', json_encode([$solicitacaoId, $autor->id, $dados], JSON_THROW_ON_ERROR));
        try {
            return DB::transaction(function () use ($solicitacaoId, $autor, $dados, $hash): self {
                $pedido = Solicitacao::query()->lockForUpdate()->findOrFail($solicitacaoId);
                Gate::forUser($autor)->authorize(Permissao::GERENCIAR_AGENDA);
                $anterior = self::query()->where('operacao_id', $dados['operacao_id'])->lockForUpdate()->first();
                if ($anterior) {
                    abort_unless(hash_equals($anterior->conteudo_hash ?? '', $hash), 409, 'Operação já utilizada com outros dados.');

                    return $anterior;
                }
                abort_unless($pedido->status === 'admitida' && $pedido->interessada_id !== null, 409, 'O pedido precisa estar admitido e vinculado à interessada.');
                abort_unless($pedido->versao === $dados['versao'], 409, 'O acompanhamento mudou. Recarregue antes de registrar o contato.');
                $ocorrido = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $dados['ocorrido_em'], 'America/Sao_Paulo')->utc();
                if ($ocorrido->isFuture()) {
                    throw ValidationException::withMessages(['ocorrido_em' => 'O contato não pode estar no futuro.']);
                }
                $contato = new self;
                $contato->forceFill(collect($dados)->except(['versao', 'ocorrido_em'])->all() + [
                    'conteudo_hash' => $hash, 'contexto' => 'acompanhamento',
                    'registrado_por_user_id' => $autor->id, 'ocorrido_em' => $ocorrido,
                    'identidade_conferida' => false, 'duvida_esclarecida' => false,
                ])->save();
                $contato->solicitacoes()->attach($pedido->id);
                $pedido->versao++;
                $pedido->save();

                return $contato;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (! self::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue antes de continuar.');
        }
    }

    protected function casts(): array
    {
        return [
            'identidade_conferida' => 'boolean',
            'duvida_esclarecida' => 'boolean',
            'ocorrido_em' => 'immutable_datetime',
        ];
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function solicitacoes(): BelongsToMany
    {
        return $this->belongsToMany(Solicitacao::class, 'contato_pedidos', 'contato_id', 'solicitacao_id');
    }

    public function resolucoes(): HasMany
    {
        return $this->hasMany(ResolucaoSolicitacao::class, 'contato_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'contato_confirmacao_id');
    }
}
