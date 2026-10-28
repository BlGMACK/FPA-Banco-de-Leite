<?php

namespace App\Models;

use Database\Factories\VersaoRelatorioVisitaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VersaoRelatorioVisita extends Model
{
    /** @use HasFactory<VersaoRelatorioVisitaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'versoes_relatorio_visita';

    protected $hidden = ['texto', 'motivo_correcao'];

    protected function casts(): array
    {
        return ['numero' => 'integer', 'finalizado_em' => 'immutable_datetime'];
    }

    public function relatorio(): BelongsTo
    {
        return $this->belongsTo(RelatorioVisita::class, 'relatorio_visita_id');
    }

    public function versaoBase(): BelongsTo
    {
        return $this->belongsTo(self::class, 'versao_base_id');
    }

    public function correcoes(): HasMany
    {
        return $this->hasMany(self::class, 'versao_base_id');
    }

    public function finalizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalizado_por_user_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAtendimento::class, 'versao_relatorio_id');
    }
}
