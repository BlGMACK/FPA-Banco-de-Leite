<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
            'desativado_em' => 'immutable_datetime',
        ];
    }

    public function desativar(User $autor, string $motivo): bool
    {
        return $this->alterarSituacao($autor, $motivo, true);
    }

    public function reativar(User $autor, string $motivo): bool
    {
        return $this->alterarSituacao($autor, $motivo, false);
    }

    private function alterarSituacao(User $autor, string $motivo, bool $desativar): bool
    {
        Gate::forUser($autor)->authorize('gerenciar-permissoes');

        if (trim($motivo) === '') {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo da alteração da conta.']);
        }

        return DB::transaction(function () use ($autor, $motivo, $desativar): bool {
            $conta = static::query()->lockForUpdate()->findOrFail($this->getKey());
            Gate::forUser($autor)->authorize('gerenciar-permissoes');

            if (! $conta->isFuncionario()) {
                throw ValidationException::withMessages(['conta' => 'Somente contas de funcionário podem ser desativadas ou reativadas.']);
            }

            if ($desativar !== $conta->estaAtivo()) {
                return false;
            }

            if ($desativar && $conta->visitasFuturas()->exists()) {
                throw ValidationException::withMessages(['conta' => 'O funcionário é responsável por visitas futuras. Reagende-as para outra pessoa ou cancele-as antes de desativar a conta.']);
            }

            $instante = now();
            $conta->forceFill([
                'desativado_em' => $desativar ? $instante : null,
                'desativado_por_user_id' => $desativar ? $autor->id : null,
            ])->save();

            $evento = new EventoConta;
            $evento->forceFill([
                'user_id' => $conta->id,
                'autor_user_id' => $autor->id,
                'acao' => $desativar ? 'desativacao' : 'reativacao',
                'motivo' => trim($motivo),
                'ocorrido_em' => $instante,
            ])->save();

            return true;
        });
    }

    public function estaAtivo(): bool
    {
        return $this->desativado_em === null;
    }

    public function visitasFuturas(): HasMany
    {
        return $this->hasMany(Agendamento::class, 'responsavel_user_id')
            ->where('status', 'confirmado')->where('inicio_previsto_em', '>', now());
    }

    public function eventosConta(): HasMany
    {
        return $this->hasMany(EventoConta::class, 'user_id');
    }

    public function desativadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'desativado_por_user_id');
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

        return $usuario?->isFuncionario() && $usuario->estaAtivo()
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
            || $this->podeRegistrarRelatoriosVisita() || $this->podeAvaliarDoadoras();
    }

    public function podeAvaliarDoadoras(): bool
    {
        return $this->possuiPermissaoAdministrativa(Permissao::AVALIAR_DOADORAS);
    }

    public function podeConsultarAvaliacoes(): bool
    {
        return $this->possuiPermissaoAdministrativa(Permissao::CONSULTAR_AVALIACOES) || $this->podeAvaliarDoadoras();
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
