<?php

namespace App\Models;

use Database\Factories\SolicitacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Solicitacao extends Model
{
    /** @use HasFactory<SolicitacaoFactory> */
    use HasFactory;

    protected $table = 'solicitacoes';

    protected function casts(): array
    {
        return [
            'dados_originais' => 'array',
            'whatsapp_disponivel' => 'boolean',
            'whatsapp_declarado_em' => 'immutable_datetime',
            'enviada_em' => 'immutable_datetime',
            'admitida_em' => 'immutable_datetime',
            'arquivada_em' => 'immutable_datetime',
            'versao' => 'integer',
        ];
    }

    public function interessada(): BelongsTo
    {
        return $this->belongsTo(Interessada::class, 'interessada_id');
    }

    public function registradaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrada_por_user_id');
    }

    public function admitidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admitida_por_user_id');
    }

    public function contatos(): BelongsToMany
    {
        return $this->belongsToMany(ContatoSolicitacao::class, 'contato_pedidos', 'solicitacao_id', 'contato_id');
    }

    public function resolucoes(): BelongsToMany
    {
        return $this->belongsToMany(ResolucaoSolicitacao::class, 'resolucao_pedidos', 'solicitacao_id', 'resolucao_id')->withPivot(['papel', 'versao_analisada', 'evidencias']);
    }

    public function eventos(): HasMany
    {
        return $this->hasMany(EventoSolicitacao::class, 'solicitacao_id');
    }
}
