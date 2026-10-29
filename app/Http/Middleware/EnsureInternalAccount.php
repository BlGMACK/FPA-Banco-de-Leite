<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalAccount
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! User::query()->whereKey($request->user()->id)->whereIn('role', ['admin', 'funcionario'])->whereNull('desativado_em')->exists()) {
            /** @var SessionGuard $guard */
            $guard = Auth::guard('web');
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403, 'O acesso ao sistema é exclusivo para a equipe do Banco de Leite.');
        }

        return $next($request);
    }
}
