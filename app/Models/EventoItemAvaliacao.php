<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoItemAvaliacao extends Model
{
    public const UPDATED_AT = null;

    public const ACOES = ['inclusao' => 'Inclusão', 'solicitacao' => 'Solicitação', 'cancelamento_solicitacao' => 'Solicitação cancelada',
        'recebimento' => 'Recebimento', 'aceite' => 'Aceite', 'complementacao' => 'Pedido de complementação',
        'prorrogacao' => 'Prorrogação', 'novo_pedido' => 'Novo pedido'];

    protected $table = 'eventos_item_avaliacao';

    protected $hidden = ['conteudo_hash'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Eventos de exames não podem ser alterados.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Eventos de exames não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return ['realizado_em' => 'immutable_date', 'valido_ate' => 'immutable_date', 'ocorrido_em' => 'immutable_datetime',
            'versao_analisada' => 'integer'];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemAvaliacao::class, 'item_avaliacao_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
