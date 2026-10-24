<?php

namespace App\Models;

use Database\Factories\ResolucaoSolicitacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResolucaoSolicitacao extends Model
{
    /** @use HasFactory<ResolucaoSolicitacaoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'resolucoes_solicitacao';

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'immutable_datetime',
        ];
    }

    public function responsavel(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsavel_user_id');
    }

    public function contato(): BelongsTo
    {
        return $this->belongsTo(ContatoSolicitacao::class, 'contato_id');
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Solicitacao::class, 'principal_id');
    }

    public function solicitacoes(): BelongsToMany
    {
        return $this->belongsToMany(Solicitacao::class, 'resolucao_pedidos', 'resolucao_id', 'solicitacao_id')->withPivot(['papel', 'versao_analisada', 'evidencias']);
    }

    public function interessadas(): BelongsToMany
    {
        return $this->belongsToMany(Interessada::class, 'resolucao_cadastros', 'resolucao_id', 'interessada_id')->withPivot(['versao_analisada', 'evidencias']);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoSolicitacao::class, 'resolucao_id');
    }
}
