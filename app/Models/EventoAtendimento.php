<?php

namespace App\Models;

use Database\Factories\EventoAtendimentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoAtendimento extends Model
{
    /** @use HasFactory<EventoAtendimentoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'eventos_atendimento';

    protected $hidden = ['conteudo_hash'];

    protected function casts(): array
    {
        return ['revisao_analisada' => 'integer', 'ocorrido_em' => 'immutable_datetime'];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(RelatorioVisita::class, 'relatorio_visita_id');
    }

    public function versaoRelatorio(): BelongsTo
    {
        return $this->belongsTo(VersaoRelatorioVisita::class, 'versao_relatorio_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
