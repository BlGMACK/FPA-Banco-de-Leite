<?php

namespace App\Models;

use Database\Factories\ResolucaoSolicitacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResolucaoSolicitacao extends Model
{
    /** @use HasFactory<ResolucaoSolicitacaoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'resolucoes_solicitacao';

    /** @param array<string, mixed> $dados */
    public static function executar(array $dados, int $origem, User $autor): self
    {
        return DB::transaction(function () use ($dados, $origem, $autor): self {
            Gate::forUser($autor)->authorize('atender-solicitacoes');
            $hash = hash('sha256', json_encode([$origem, $autor->id, $dados], JSON_THROW_ON_ERROR));
            // A trava da origem serializa retransmissões antes da consulta da operação.
            Solicitacao::query()->whereIn('id', collect($dados['pedidos'])->pluck('id'))->orderBy('id')->lockForUpdate()->get();
            $anterior = static::query()->where('operacao_id', $dados['operacao_id'])->first();
            if ($anterior) {
                abort_unless(hash_equals($anterior->conteudo_hash, $hash), 409, 'Operação já utilizada com outros dados. Recarregue a página.');

                return $anterior;
            }
            $pedidos = Solicitacao::bloquearPedidos($dados['pedidos'], $origem);
            $cadastros = collect();
            foreach (collect($dados['cadastros'] ?? [])->sortBy('id') as $entrada) {
                $cadastro = Interessada::query()->lockForUpdate()->findOrFail($entrada['id']);
                abort_unless($cadastro->versao === (int) $entrada['versao'], 409, 'O cadastro mudou. Recarregue a página.');
                self::exigir($cadastro->solicitacoes()->where('status', 'admitida')->exists(), 'cadastros', 'Selecione um cadastro já admitido.');
                $cadastros->put($cadastro->id, $cadastro);
            }
            $contato = ContatoSolicitacao::query()->findOrFail($dados['contato_id']);
            self::exigir($pedidos->keys()->diff($contato->solicitacoes()->pluck('solicitacoes.id'))->isEmpty(), 'contato_id', 'O contato precisa abranger todos os pedidos selecionados.');
            $tipo = $dados['acao'];
            if ($tipo !== 'aguardando_esclarecimento') {
                self::exigir($contato->resultado === 'realizado' && $contato->identidade_conferida && $contato->duvida_esclarecida,
                    'contato_id', 'Registre o contato realizado com identidade conferida e dúvida esclarecida antes de concluir.');
            }
            $admite = in_array($tipo, ['admitir', 'admitir_principal'], true);
            $principal = $admite ? $pedidos->get((int) ($dados['principal_id'] ?? $origem)) : null;
            if ($admite) {
                self::exigir($principal !== null && $principal->status === 'em_analise', 'principal_id', 'Escolha um pedido em análise como principal.');
                foreach (['nome_completo', 'cpf', 'email_contato', 'municipio', 'bairro', 'uf', 'cep', 'logradouro', 'numero'] as $campo) {
                    self::exigir(filled($principal->{$campo}), 'principal_id', 'Confira os dados obrigatórios do pedido antes da admissão.');
                }
                self::exigir($principal->whatsapp_disponivel && filled($principal->{'telefone_'.$principal->whatsapp_telefone}),
                    'principal_id', 'O pedido precisa ter um telefone disponível para WhatsApp.');
                self::exigir(in_array($dados['identidade'] ?? null, ['nova', 'existente'], true), 'identidade', 'Escolha explicitamente uma identidade nova ou existente.');
                if ($tipo === 'admitir') {
                    self::exigir($pedidos->count() === 1, 'pedidos', 'A admissão simples aceita somente um pedido.');
                } else {
                    self::exigir($pedidos->count() > 1, 'pedidos', 'Selecione o principal e os pedidos repetidos.');
                }
            }
            if ($tipo === 'arquivar_selecionados') {
                self::exigir($pedidos->count() > 1 || $cadastros->isNotEmpty(), 'pedidos', 'Selecione pedidos repetidos ou um pedido com referência a cadastro concluído.');
            }
            $origemPedido = $pedidos[$origem];
            $correspondencias = $origemPedido->correspondenciasAtuais();
            foreach ($pedidos->except($origem) as $pedido) {
                self::exigir($correspondencias->contains(fn (array $par): bool => $par['tipo'] === 'pedido' && $par['registro']->id === $pedido->id), 'pedidos', 'Selecione apenas correspondências atuais do pedido de origem.');
            }
            foreach ($cadastros as $cadastro) {
                self::exigir($correspondencias->contains(fn (array $par): bool => $par['tipo'] === 'cadastro' && $par['registro']->id === $cadastro->id), 'cadastros', 'O cadastro deve corresponder ao pedido de origem.');
            }
            if (in_array($tipo, ['pessoas_distintas', 'aguardando_esclarecimento'], true)) {
                self::exigir($pedidos->count() > 1 || $cadastros->isNotEmpty(), 'pedidos', 'Selecione a correspondência que foi examinada.');
            }
            $identidade = null;
            $evidenciasPorPedido = [];
            foreach ($pedidos as $pedido) {
                $evidenciasPorPedido[$pedido->id] = $pedido->correspondenciasAtuais()->map(fn (array $par): array => [
                    'tipo' => $par['tipo'], 'id' => $par['registro']->id, 'campos' => $par['campos'],
                ])->all();
            }
            if ($admite) {
                if ($dados['identidade'] === 'existente') {
                    $identidade = $cadastros->get((int) ($dados['cadastro_referencia_id'] ?? 0));
                    self::exigir($identidade !== null, 'cadastro_referencia_id', 'Selecione e confira o cadastro existente de referência.');
                    self::exigir($principal->correspondenciasAtuais()->contains(fn (array $par): bool => $par['tipo'] === 'cadastro' && $par['registro']->id === $identidade->id),
                        'cadastro_referencia_id', 'O cadastro de referência deve corresponder ao pedido principal.');
                }
                foreach ($principal->correspondenciasAtuais() as $par) {
                    $incluido = $par['tipo'] === 'pedido' && $pedidos->has($par['registro']->id);
                    $vinculado = $par['tipo'] === 'cadastro' && $identidade?->id === $par['registro']->id;
                    self::exigir($incluido || $vinculado || $principal->correspondenciaRevisada($par['registro'], $par['tipo']),
                        'pedidos', 'Há correspondências pendentes fora desta decisão. Confira-as antes da admissão.');
                }
                if (! $identidade) {
                    $identidade = new Interessada;
                    $identidade->forceFill($principal->only([
                        'nome_completo', 'nome_normalizado', 'cpf', 'telefone_fixo', 'telefone_celular',
                        'email_contato', 'municipio', 'bairro', 'uf', 'cep', 'logradouro', 'numero', 'complemento',
                        'whatsapp_telefone', 'whatsapp_disponivel', 'whatsapp_declarado_em',
                    ]) + ['confirmada_por_user_id' => $autor->id, 'confirmada_em' => now(), 'versao' => 1])->save();
                }
            }
            $resolucao = new self;
            $resolucao->forceFill([
                'operacao_id' => $dados['operacao_id'], 'conteudo_hash' => $hash, 'tipo' => $tipo,
                'contato_id' => $contato->id, 'responsavel_user_id' => $autor->id,
                'principal_id' => $principal?->id, 'motivo' => $dados['motivo'], 'ocorrido_em' => now(),
            ])->save();
            foreach ($pedidos as $pedido) {
                $pares = [];
                foreach ($pedidos->except($pedido->id) as $alvo) {
                    $pares[] = $pedido->assinaturaCorrespondencia($alvo, 'pedido');
                }
                foreach ($cadastros as $alvo) {
                    $pares[] = $pedido->assinaturaCorrespondencia($alvo, 'cadastro');
                }
                $estado = $pedido->status;
                $papel = 'analisado';
                $evento = 'conferencia';
                $versao = $pedido->versao;
                if ($admite && $pedido->id === $principal->id) {
                    $pedido->forceFill(['status' => 'admitida', 'interessada_id' => $identidade->id,
                        'admitida_por_user_id' => $autor->id, 'admitida_em' => now()]);
                    $papel = 'principal';
                    $evento = 'admissao';
                } elseif (in_array($tipo, ['arquivar_selecionados', 'admitir_principal'], true)) {
                    $pedido->forceFill(['status' => 'arquivada', 'arquivada_em' => now()]);
                    $papel = 'arquivado';
                    $evento = 'arquivamento';
                }
                $resolucao->solicitacoes()->attach($pedido->id, ['papel' => $papel, 'versao_analisada' => $versao,
                    'evidencias' => json_encode([
                        'pares' => $pares,
                        'correspondencias' => $evidenciasPorPedido[$pedido->id],
                    ], JSON_THROW_ON_ERROR)]);
                $pedido->versao++;
                $pedido->save();
                $pedido->registrarEvento($evento, $autor, $estado, $resolucao);
            }
            foreach ($cadastros as $cadastro) {
                $resolucao->interessadas()->attach($cadastro->id, ['versao_analisada' => $cadastro->versao,
                    'evidencias' => json_encode(['referencia' => true], JSON_THROW_ON_ERROR)]);
            }

            return $resolucao;
        });
    }

    private static function exigir(bool $condicao, string $campo, string $mensagem): void
    {
        if (! $condicao) {
            throw ValidationException::withMessages([$campo => $mensagem])->errorBag('decisao');
        }
    }

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'immutable_datetime',
        ];
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function contato(): BelongsTo
    {
        return $this->belongsTo(ContatoSolicitacao::class, 'contato_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'principal_id');
    }

    public function solicitacoes(): BelongsToMany
    {
        return $this->belongsToMany(Solicitacao::class, 'resolucao_pedidos', 'resolucao_id', 'solicitacao_id')->withPivot(['papel', 'versao_analisada', 'evidencias']);
    }

    public function interessadas(): BelongsToMany
    {
        return $this->belongsToMany(Interessada::class, 'resolucao_cadastros', 'resolucao_id', 'interessada_id')->withPivot(['versao_analisada', 'evidencias']);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoSolicitacao::class, 'resolucao_id');
    }
}
