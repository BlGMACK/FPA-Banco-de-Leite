<?php

namespace App\Models;

use Database\Factories\EventoAgendamentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoAgendamento extends Model
{
    /** @use HasFactory<EventoAgendamentoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'eventos_agendamento';

    protected function casts(): array
    {
        return ['ocorrido_em' => 'immutable_datetime', 'versao_analisada' => 'integer',
            'dados_anteriores' => 'array', 'dados_novos' => 'array'];
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function agendamentoResultante(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_resultante_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
