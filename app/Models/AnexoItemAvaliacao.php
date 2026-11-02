<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        static::updating(function (): void {
            throw new \LogicException('Anexos registrados não podem ser alterados.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Anexos registrados não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return ['tamanho' => 'integer', 'enviado_em' => 'immutable_datetime'];
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
}
