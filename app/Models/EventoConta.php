<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventoConta extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'eventos_conta';

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

    public function autor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autor_user_id');
    }
}
