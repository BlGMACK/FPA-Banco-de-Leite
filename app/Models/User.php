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
        return $this->possuiPermissaoAdministrativa(Permissao::ATENDER_SOLICITACOES);
    }

    public function podeGerenciarAgenda(): bool
    {
        return $this->possuiPermissaoAdministrativa(Permissao::GERENCIAR_AGENDA);
    }

    private function possuiPermissaoAdministrativa(string $codigo): bool
    {
        $usuario = $this->exists ? static::query()->find($this->getKey()) : null;

        if ($usuario?->isAdmin()) {
            return true;
        }

        return $usuario?->isFuncionario()
            && $usuario->concessoes()->whereNull('revogada_em')
                ->whereHas('permissao', fn (Builder $query): Builder => $query->where('codigo', $codigo))
                ->exists();
    }

    public function podeCriarFuncionarios(): bool
    {
        return $this->possuiPermissaoAdministrativa(Permissao::CRIAR_FUNCIONARIOS);
    }

    public function podeRegistrarRelatoriosVisita(): bool
    {
        return $this->possuiPermissaoAdministrativa(Permissao::REGISTRAR_RELATORIOS_VISITA);
    }

    public function podeConsultarRelatoriosVisita(): bool
    {
        return $this->possuiPermissaoAdministrativa(Permissao::CONSULTAR_RELATORIOS_VISITA)
            || $this->podeRegistrarRelatoriosVisita();
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
}
