<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Este é o "porteiro" de cada rota protegida. Ele roda ANTES do Controller,
// então se o acesso for negado, o Controller nem chega a ser executado —
// é isso que torna a permissão algo garantido pelo servidor, e não apenas
// uma tela escondida que um usuário curioso poderia acessar digitando a URL.
//
// Uso nas rotas (veja routes/web.php):
//   Route::get('/admin/...', ...)->middleware('role:admin');
//   Route::get('/interno/...', ...)->middleware('role:admin,funcionario');
//
// O parâmetro depois dos dois-pontos ("admin", ou "admin,funcionario") chega
// aqui como $roles — por isso a assinatura usa "...$roles" (variádico): um
// mesmo middleware aceita tanto "só um papel" quanto "qualquer um destes".
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        // Sem usuário autenticado, não há papel a verificar — devolve para
        // a tela de login em vez de deixar passar ou travar com erro 500.
        if (!$usuario) {
            return redirect()->route('login');
        }

        // in_array com o papel do usuário contra a lista de papéis permitidos
        // nesta rota. Se o papel dele não estiver na lista, 403 (Forbidden) —
        // o código HTTP correto para "autenticado, mas sem permissão aqui".
        if (!in_array($usuario->role, $roles, true)) {
            abort(403, 'Você não tem permissão para acessar esta página.');
        }

        return $next($request);
    }
}