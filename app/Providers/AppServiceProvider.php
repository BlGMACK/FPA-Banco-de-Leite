<?php

namespace App\Providers;

use App\Models\Permissao;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define(Permissao::ATENDER_SOLICITACOES, fn (User $user): bool => $user->podeAtenderSolicitacoes());
        Gate::define('gerenciar-permissoes', fn (User $user): bool => $user->podeGerenciarPermissoes());
    }
}
