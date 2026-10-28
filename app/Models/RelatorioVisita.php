<?php

namespace App\Models;

use Database\Factories\RelatorioVisitaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RelatorioVisita extends Model
{
    /** @use HasFactory<RelatorioVisitaFactory> */
    use HasFactory;

    protected $table = 'relatorios_visita';

    protected $hidden = ['texto_rascunho', 'motivo_correcao'];

    protected function casts(): array
    {
        return ['numero_versao_vigente' => 'integer', 'base_correcao_numero' => 'integer', 'revisao' => 'integer'];
    }

    public function atendimento(): BelongsTo
    {
        return $this->belongsTo(Atendimento::class, 'atendimento_id');
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'criado_por_user_id');
    }

    public function ultimoEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ultimo_editor_user_id');
    }

    public function versoes(): HasMany
    {
        return $this->hasMany(VersaoRelatorioVisita::class, 'relatorio_visita_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAtendimento::class, 'relatorio_visita_id');
    }
}
