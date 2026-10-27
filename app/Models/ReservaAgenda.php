<?php

namespace App\Models;

use Database\Factories\ReservaAgendaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReservaAgenda extends Model
{
    /** @use HasFactory<ReservaAgendaFactory> */
    use HasFactory;

    protected $table = 'reservas_agenda';

    protected $primaryKey = 'solicitacao_id';

    public $incrementing = false;

    public $timestamps = false;

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'solicitacao_id');
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }
}
