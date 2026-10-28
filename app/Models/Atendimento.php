<?php

namespace App\Models;

use Database\Factories\AtendimentoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Atendimento extends Model
{
    /** @use HasFactory<AtendimentoFactory> */
    use HasFactory;

    protected $table = 'atendimentos';

    protected $hidden = ['motivo_divergencia'];

    protected function casts(): array
    {
        return ['inicio_efetivo_em' => 'immutable_datetime', 'fim_efetivo_em' => 'immutable_datetime', 'registrado_em' => 'immutable_datetime'];
    }

    public function agendamento(): BelongsTo
    {
        return $this->belongsTo(Agendamento::class, 'agendamento_id');
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function relatorio(): HasOne
    {
        return $this->hasOne(RelatorioVisita::class, 'atendimento_id');
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoAtendimento::class, 'atendimento_id');
    }
}
