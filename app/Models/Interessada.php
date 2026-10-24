<?php

namespace App\Models;

use Database\Factories\InteressadaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Interessada extends Model
{
    /** @use HasFactory<InteressadaFactory> */
    use HasFactory;

    protected $table = 'interessadas';

    protected function casts(): array
    {
        return [
            'whatsapp_disponivel' => 'boolean',
            'whatsapp_declarado_em' => 'immutable_datetime',
            'confirmada_em' => 'immutable_datetime',
            'versao' => 'integer',
        ];
    }

    public function confirmadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmada_por_user_id');
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(Solicitacao::class, 'interessada_id');
    }

    public function resolucoes(): BelongsToMany
    {
        return $this->belongsToMany(ResolucaoSolicitacao::class, 'resolucao_cadastros', 'interessada_id', 'resolucao_id')->withPivot(['versao_analisada', 'evidencias']);
    }
}
