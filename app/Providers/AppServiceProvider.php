<?php

namespace App\Providers;

use App\Models\Permissao;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
        Paginator::useBootstrapFive();
        RateLimiter::for('receber-solicitacoes', function (Request $request): Limit {
            return $request->routeIs('interno.*')
                ? Limit::perMinute(30)->by('interno:'.$request->user()->id)
                : Limit::perMinute(5)->by('publico:'.$request->ip());
        });
        Gate::define(Permissao::ATENDER_SOLICITACOES, fn (User $user): bool => $user->podeAtenderSolicitacoes());
        Gate::define(Permissao::GERENCIAR_AGENDA, fn (User $user): bool => $user->podeGerenciarAgenda());
        Gate::define(Permissao::CRIAR_FUNCIONARIOS, fn (User $user): bool => $user->podeCriarFuncionarios());
        Gate::define(Permissao::CONSULTAR_RELATORIOS_VISITA, fn (User $user): bool => $user->podeConsultarRelatoriosVisita());
        Gate::define(Permissao::REGISTRAR_RELATORIOS_VISITA, fn (User $user): bool => $user->podeRegistrarRelatoriosVisita());
        Gate::define('gerenciar-permissoes', fn (User $user): bool => $user->podeGerenciarPermissoes());
    }
}
