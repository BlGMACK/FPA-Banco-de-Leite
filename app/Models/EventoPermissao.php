<?php

namespace App\Models;

use Database\Factories\EventoPermissaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoPermissao extends Model
{
    /** @use HasFactory<EventoPermissaoFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'eventos_permissao';

    protected function casts(): array
    {
        return [
            'ocorrido_em' => 'immutable_datetime',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function permissao(): BelongsTo
    {
        return $this->belongsTo(Permissao::class, 'permissao_id');
    }

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
