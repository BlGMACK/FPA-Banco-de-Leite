<?php

namespace App\Models;

use Database\Factories\DecisaoAvaliacaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisaoAvaliacao extends Model
{
    /** @use HasFactory<DecisaoAvaliacaoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'decisoes_avaliacao';

    protected $hidden = ['conteudo_hash'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Decisões registradas não podem ser alteradas.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Decisões registradas não podem ser excluídas.');
        });
    }

    protected function casts(): array
    {
        return ['versao_analisada' => 'integer', 'decidido_em' => 'immutable_datetime', 'possui_plano_saude' => 'boolean', 'valida_ate' => 'immutable_date'];
    }

    public function avaliacao(): BelongsTo
    {
        return $this->belongsTo(Avaliacao::class, 'avaliacao_id');
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
