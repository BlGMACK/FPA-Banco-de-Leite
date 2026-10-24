<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function concessoes(): HasMany
    {
        return $this->hasMany(ConcessaoPermissao::class, 'user_id');
    }

    public function eventosPermissao(): HasMany
    {
        return $this->hasMany(EventoPermissao::class, 'user_id');
    }

    public function podeGerenciarPermissoes(): bool
    {
        return $this->exists && static::query()->whereKey($this->getKey())->where('role', 'admin')->exists();
    }

    public function podeAtenderSolicitacoes(): bool
    {
        $usuario = $this->exists ? static::query()->find($this->getKey()) : null;

        if ($usuario?->isAdmin()) {
            return true;
        }

        return $usuario?->isFuncionario()
            && $usuario->concessoes()->whereNull('revogada_em')
                ->whereHas('permissao', fn (Builder $query): Builder => $query->where('codigo', Permissao::ATENDER_SOLICITACOES))
                ->exists();
    }

    // Métodos auxiliares de leitura — usados tanto pelo middleware quanto
    // pelas views Blade (ex.: @if($user->isAdmin()) para esconder um botão).
    // Mantêm o nome do papel ("admin", "funcionario"...) como o único lugar
    // onde essa string literal aparece no código de verificação.
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isFuncionario(): bool
    {
        return $this->role === 'funcionario';
    }

    public function isDoadora(): bool
    {
        return $this->role === 'doadora';
    }
}
