<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\EventoSolicitacaoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventoSolicitacao extends Model
{
    /** @use HasFactory<EventoSolicitacaoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'eventos_solicitacao';

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'immutable_datetime',
            'valores_descartados_em' => 'immutable_datetime',
            'dados_anteriores' => 'array',
            'dados_novos' => 'array',
        ];
    }

    /** Correções feitas até o limite que ainda guardam os valores anteriores e novos. */
    public function scopeCorrecoesVencidas(Builder $query, CarbonInterface $limite): Builder
    {
        return $query->where('tipo', 'correcao')->whereNull('valores_descartados_em')->where('ocorrido_em', '<=', $limite->copy()->utc());
    }

    /** Campos alterados pela correção, preservados mesmo depois do descarte dos valores. */
    public function camposCorrigidos(): array
    {
        return array_keys($this->dados_novos['campos'] ?? []);
    }

    /** Troca os valores da correção por nulos, mantendo registro, campos, versão, autor, motivo e data. */
    public function descartarValores(User $autor, CarbonInterface $limite): bool
    {
        return DB::transaction(function () use ($autor, $limite): bool {
            $evento = static::query()->correcoesVencidas($limite)->lockForUpdate()->find($this->getKey());
            Gate::forUser($autor)->authorize('gerenciar-retencao');
            if (! $evento) {
                return false;
            }
            $vazios = array_fill_keys($evento->camposCorrigidos(), null);
            $evento->forceFill([
                'dados_anteriores' => [...$evento->dados_anteriores, 'campos' => $vazios],
                'dados_novos' => [...$evento->dados_novos, 'campos' => $vazios],
                'valores_descartados_em' => now()->utc(), 'valores_descartados_por_user_id' => $autor->id,
            ])->save();

            return true;
        });
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }

    public function resolucao(): BelongsTo
    {
        return $this->belongsTo(ResolucaoSolicitacao::class, 'resolucao_id');
    }
}
