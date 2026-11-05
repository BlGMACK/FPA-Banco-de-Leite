<?php

namespace App\Models;

use Database\Factories\SolicitacaoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Solicitacao extends Model
{
    /** @use HasFactory<SolicitacaoFactory> */
    use HasFactory;

    protected $table = 'solicitacoes';

    /** @param array<int, array{id: int|string, versao: int|string}> $entradas */
    public static function bloquearPedidos(array $entradas, int $origem): Collection
    {
        $versoes = collect($entradas)->pluck('versao', 'id');
        abort_unless($versoes->has($origem), 422, 'Inclua o pedido de origem.');
        $pedidos = static::query()->whereIn('id', $versoes->keys())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        abort_unless($pedidos->count() === $versoes->count(), 404);
        foreach ($pedidos as $pedido) {
            abort_unless(in_array($pedido->status, ['enviada', 'em_analise'], true)
                && $pedido->versao === (int) $versoes[$pedido->id], 409, 'O pedido mudou. Recarregue a página e confira os dados antes de decidir.');
        }

        return $pedidos;
    }

    public function registrarEvento(string $tipo, User $autor, string $estadoAnterior, ?ResolucaoSolicitacao $resolucao = null): void
    {
        $evento = new EventoSolicitacao;
        $evento->forceFill([
            'solicitacao_id' => $this->id, 'autor_user_id' => $autor->id,
            'resolucao_id' => $resolucao?->id, 'tipo' => $tipo,
            'estado_anterior' => $estadoAnterior, 'estado_novo' => $this->status,
            'dados_novos' => ['versao' => $this->versao, 'interessada_id' => $this->interessada_id],
            'motivo' => $resolucao?->motivo, 'ocorrido_em' => now(),
        ])->save();
    }

    public function assinaturaCorrespondencia(Model $alvo, string $tipo): string
    {
        $campos = ['cpf', 'nome_normalizado', 'email_contato', 'telefone_fixo', 'telefone_celular'];

        return hash('sha256', json_encode([$tipo, $alvo->id, $this->only($campos), $alvo->only($campos)], JSON_THROW_ON_ERROR));
    }

    public function correspondenciaRevisada(Model $alvo, string $tipo): bool
    {
        $assinatura = $this->assinaturaCorrespondencia($alvo, $tipo);
        $resolucoes = $this->relationLoaded('resolucoes') ? $this->resolucoes : $this->resolucoes()->get();
        foreach ($resolucoes->whereIn('tipo', ['pessoas_distintas', 'aguardando_esclarecimento'])->sortByDesc('id') as $resolucao) {
            $evidencias = json_decode($resolucao->pivot->evidencias ?? '[]', true);
            if (in_array($assinatura, $evidencias['pares'] ?? [], true)) {
                return $resolucao->tipo === 'pessoas_distintas';
            }
        }

        return false;
    }

    public static function consultaCorrespondencias(bool $cadastro): QueryBuilder
    {
        $query = DB::table(($cadastro ? 'interessadas' : 'solicitacoes').' as alvo')->select('alvo.id')
            ->whereIn('solicitacoes.status', ['enviada', 'em_analise']);
        if ($cadastro) {
            $query->whereExists(function (QueryBuilder $origem): void {
                $origem->selectRaw('1')->from('solicitacoes as origem')
                    ->whereColumn('origem.interessada_id', 'alvo.id')->where('origem.status', 'admitida');
            })->where(function (QueryBuilder $vinculo): void {
                $vinculo->whereNull('solicitacoes.interessada_id')->orWhereColumn('alvo.id', '!=', 'solicitacoes.interessada_id');
            });
        } else {
            $query->whereIn('alvo.status', ['enviada', 'em_analise'])->whereColumn('alvo.id', '!=', 'solicitacoes.id');
        }

        return $query->where(function (QueryBuilder $dados): void {
            foreach (['cpf', 'nome_normalizado', 'email_contato'] as $campo) {
                $dados->orWhere(function (QueryBuilder $campoQuery) use ($campo): void {
                    $campoQuery->where('solicitacoes.'.$campo, '!=', '');
                    if ($campo === 'email_contato') {
                        $campoQuery->whereRaw('LOWER(alvo.email_contato) = LOWER(solicitacoes.email_contato)');
                    } else {
                        $campoQuery->whereColumn('alvo.'.$campo, 'solicitacoes.'.$campo);
                    }
                });
            }
            foreach (['telefone_fixo', 'telefone_celular'] as $campo) {
                foreach (['telefone_fixo', 'telefone_celular'] as $outro) {
                    $dados->orWhere(function (QueryBuilder $telefone) use ($campo, $outro): void {
                        $telefone->where('solicitacoes.'.$campo, '!=', '')->whereColumn('alvo.'.$outro, 'solicitacoes.'.$campo);
                    });
                }
            }
        });
    }

    public function correspondenciasAtuais(): Collection
    {
        if (! in_array($this->status, ['enviada', 'em_analise'], true)) {
            return collect();
        }
        $pedidos = static::query()->whereKeyNot($this->id)->whereIn('status', ['enviada', 'em_analise']);
        $cadastros = Interessada::query()->whereHas('solicitacoes', fn (Builder $query): Builder => $query->where('status', 'admitida'));
        if ($this->interessada_id !== null) {
            $cadastros->whereKeyNot($this->interessada_id);
        }
        $resultado = collect();
        foreach (['pedido' => $pedidos, 'cadastro' => $cadastros] as $tipo => $query) {
            $query->where(function (Builder $dados): void {
                foreach (['cpf', 'nome_normalizado', 'email_contato'] as $campo) {
                    if ($this->{$campo} !== null && $this->{$campo} !== '') {
                        if ($campo === 'email_contato') {
                            $dados->orWhereRaw('LOWER(email_contato) = ?', [Str::lower($this->{$campo})]);
                        } else {
                            $dados->orWhere($campo, $this->{$campo});
                        }
                    }
                }
                $telefones = array_filter([$this->telefone_fixo, $this->telefone_celular]);
                $dados->orWhereIn('telefone_fixo', $telefones)->orWhereIn('telefone_celular', $telefones);
            });
            foreach ($query->orderBy('id')->get() as $alvo) {
                $campos = [];
                foreach (['cpf' => 'CPF', 'nome_normalizado' => 'Nome', 'email_contato' => 'E-mail'] as $campo => $rotulo) {
                    if ($this->{$campo} && Str::lower($this->{$campo}) === Str::lower($alvo->{$campo})) {
                        $campos[] = $rotulo;
                    }
                }
                if (array_intersect(array_filter([$this->telefone_fixo, $this->telefone_celular]), array_filter([$alvo->telefone_fixo, $alvo->telefone_celular]))) {
                    $campos[] = 'Telefone';
                }
                $resultado->push(['tipo' => $tipo, 'registro' => $alvo, 'campos' => $campos]);
            }
        }

        return $resultado;
    }

    /**
     * Pedidos que aceitam agendar visita: ainda sem visita realizada, ou com a avaliação em aguardo e o relatório de todas
     * as visitas já analisado, caso em que a nova visita complementa a mesma avaliação. Não considera a reserva da agenda.
     */
    public function scopeAceitandoVisita(Builder $query): Builder
    {
        return $query->where(fn (Builder $pedido): Builder => $pedido->whereDoesntHave('agendamentos.atendimento')
            ->orWhereExists(fn (QueryBuilder $avaliacao): QueryBuilder => $avaliacao->selectRaw('1')->from('avaliacoes')
                ->join('atendimentos as primeira_visita', 'primeira_visita.id', '=', 'avaliacoes.atendimento_id')
                ->join('agendamentos as primeira_agenda', 'primeira_agenda.id', '=', 'primeira_visita.agendamento_id')
                ->whereColumn('primeira_agenda.solicitacao_id', 'solicitacoes.id')->where('avaliacoes.estado', 'em_aguardo')
                ->whereNotExists(fn (QueryBuilder $visita): QueryBuilder => $visita->selectRaw('1')->from('atendimentos as visita')
                    ->join('agendamentos as agenda_visita', 'agenda_visita.id', '=', 'visita.agendamento_id')
                    ->whereColumn('agenda_visita.solicitacao_id', 'solicitacoes.id')
                    ->whereNotExists(fn (QueryBuilder $decisao): QueryBuilder => $decisao->selectRaw('1')->from('decisoes_avaliacao')
                        ->join('versoes_relatorio_visita', 'versoes_relatorio_visita.id', '=', 'decisoes_avaliacao.versao_relatorio_id')
                        ->join('relatorios_visita', 'relatorios_visita.id', '=', 'versoes_relatorio_visita.relatorio_visita_id')
                        ->whereColumn('decisoes_avaliacao.avaliacao_id', 'avaliacoes.id')
                        ->whereColumn('relatorios_visita.atendimento_id', 'visita.id')))));
    }

    public function aceitaVisita(): bool
    {
        return static::query()->whereKey($this->id)->aceitandoVisita()->exists();
    }

    protected function casts(): array
    {
        return [
            'dados_originais' => 'array',
            'whatsapp_disponivel' => 'boolean',
            'whatsapp_declarado_em' => 'immutable_datetime',
            'enviada_em' => 'immutable_datetime',
            'admitida_em' => 'immutable_datetime',
            'arquivada_em' => 'immutable_datetime',
            'versao' => 'integer',
        ];
    }

    public function interessada(): BelongsTo
    {
        return $this->belongsTo(Interessada::class, 'interessada_id');
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_user_id');
    }

    public function admitidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitida_por_user_id');
    }

    public function contatos(): BelongsToMany
    {
        return $this->belongsToMany(ContatoSolicitacao::class, 'contato_pedidos', 'solicitacao_id', 'contato_id');
    }

    public function resolucoes(): BelongsToMany
    {
        return $this->belongsToMany(ResolucaoSolicitacao::class, 'resolucao_pedidos', 'solicitacao_id', 'resolucao_id')->withPivot(['papel', 'versao_analisada', 'evidencias']);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoSolicitacao::class, 'solicitacao_id');
    }

    public function agendamentos(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'solicitacao_id');
    }

    public function reservaAgenda(): HasOne
    {
        return $this->hasOne(ReservaAgenda::class, 'solicitacao_id');
    }
}
