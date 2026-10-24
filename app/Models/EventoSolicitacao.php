<?php

namespace App\Models;

use Database\Factories\EventoSolicitacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
            'dados_anteriores' => 'array',
            'dados_novos' => 'array',
        ];
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
