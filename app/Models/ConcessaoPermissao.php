<?php

namespace App\Models;

use Database\Factories\ConcessaoPermissaoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConcessaoPermissao extends Model
{
    /** @use HasFactory<ConcessaoPermissaoFactory> */
    use HasFactory;

    protected $table = 'permissao_user';

    protected function casts(): array
    {
        return [
            'concedida_em' => 'immutable_datetime',
            'revogada_em' => 'immutable_datetime',
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

    public function concedidaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concedida_por_user_id');
    }

    public function revogadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revogada_por_user_id');
    }
}
