<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role'])]
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
