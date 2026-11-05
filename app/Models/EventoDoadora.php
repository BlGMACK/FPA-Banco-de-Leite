<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoDoadora extends Model
{
    public const UPDATED_AT = null;

    public const ACOES = ['suspensao' => 'Suspensão', 'retomada' => 'Retomada', 'renovacao' => 'Renovação do prazo',
        'conversao_definitiva' => 'Conversão em definitiva', 'encerramento' => 'Encerramento', 'revogacao' => 'Revogação'];

    protected $table = 'eventos_doadora';

    protected $hidden = ['conteudo_hash'];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Eventos de doadoras não podem ser alterados.');
        });
        static::deleting(function (): void {
            throw new \LogicException('Eventos de doadoras não podem ser excluídos.');
        });
    }

    protected function casts(): array
    {
        return ['valida_ate' => 'immutable_date', 'ocorrido_em' => 'immutable_datetime', 'versao_analisada' => 'integer'];
    }

    public function doadora(): BelongsTo
    {
        return $this->belongsTo(Doadora::class, 'doadora_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
