<?php

namespace App\Models;

use Database\Factories\ContatoSolicitacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContatoSolicitacao extends Model
{
    /** @use HasFactory<ContatoSolicitacaoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'contatos_solicitacao';

    protected function casts(): array
    {
        return [
            'identidade_conferida' => 'boolean',
            'duvida_esclarecida' => 'boolean',
            'ocorrido_em' => 'immutable_datetime',
        ];
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por_user_id');
    }

    public function solicitacoes(): BelongsToMany
    {
        return $this->belongsToMany(Solicitacao::class, 'contato_pedidos', 'contato_id', 'solicitacao_id');
    }

    public function resolucoes(): HasMany
    {
        return $this->hasMany(ResolucaoSolicitacao::class, 'contato_id');
    }
}
