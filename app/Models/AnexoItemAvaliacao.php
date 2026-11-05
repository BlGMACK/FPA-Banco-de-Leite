<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AnexoItemAvaliacao extends Model
{
    public const UPDATED_AT = null;

    /** Tipos aceitos, conferidos pelo conteúdo do arquivo; a extensão gravada vem daqui, nunca do nome enviado. */
    public const TIPOS = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    public const TAMANHO_MAXIMO_KB = 10240;

    protected $table = 'anexos_item_avaliacao';

    protected $hidden = ['caminho', 'conteudo_hash'];

    protected static function booted(): void
    {
        // A única alteração permitida é a marcação do descarte por retenção, feita uma vez.
        static::updating(function (self $anexo): void {
            if ($anexo->getOriginal('descartado_em') !== null || array_diff(array_keys($anexo->getDirty()), ['descartado_em', 'descartado_por_user_id'])) {
                throw new \LogicException('Anexos registrados não podem ser alterados.');
            }
        });
        static::deleting(function (): void {
            throw new \LogicException('Anexos registrados não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return ['tamanho' => 'integer', 'enviado_em' => 'immutable_datetime', 'descartado_em' => 'immutable_datetime'];
    }

    /** Anexos ainda guardados de avaliações cuja decisão de habilitação ocorreu até o limite. */
    public function scopeVencidos(Builder $query, CarbonInterface $limite): Builder
    {
        return $query->whereNull('descartado_em')->whereHas('item.avaliacao.decisoes', fn (Builder $decisao): Builder => $decisao
            ->where('etapa', 'habilitacao')->where('decidido_em', '<=', $limite->copy()->utc()));
    }

    /**
     * Apaga o arquivo e marca o registro com data e autor; nome, tamanho e hash continuam no histórico.
     * Se a marcação falhar depois de o arquivo sair do disco, o download responde 404 e o próximo descarte conclui a marcação.
     */
    public function descartar(User $autor, CarbonInterface $limite): bool
    {
        return DB::transaction(function () use ($autor, $limite): bool {
            $anexo = static::query()->vencidos($limite)->lockForUpdate()->find($this->getKey());
            Gate::forUser($autor)->authorize('gerenciar-retencao');
            if (! $anexo) {
                return false;
            }
            Storage::disk('anexos')->delete($anexo->caminho);
            $anexo->forceFill(['descartado_em' => now()->utc(), 'descartado_por_user_id' => $autor->id])->save();

            return true;
        });
    }

    public function tamanhoLegivel(): string
    {
        return $this->tamanho >= 1048576 ? number_format($this->tamanho / 1048576, 1, ',', '.').' MB' : max(1, (int) round($this->tamanho / 1024)).' KB';
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemAvaliacao::class, 'item_avaliacao_id');
    }

    public function enviadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviado_por_user_id');
    }

    public function descartadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'descartado_por_user_id');
    }
}
