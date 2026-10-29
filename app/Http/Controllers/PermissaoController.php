<?php

namespace App\Http\Controllers;

use App\Models\Permissao;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PermissaoController extends Controller
{
    public function index(): View
    {
        $funcionarios = User::query()->where('role', 'funcionario')
            ->with(['concessoes' => fn (HasMany $query): HasMany => $query->whereNull('revogada_em')->with('permissao')])
            ->orderBy('name')->orderBy('id')->paginate(20);

        return view('permissoes.index', ['funcionarios' => $funcionarios]);
    }

    public function show(int $funcionario): View
    {
        $pessoa = $this->funcionario($funcionario);

        return view('permissoes.show', [
            'funcionario' => $pessoa,
            'catalogo' => Permissao::query()->orderBy('id')->get(),
            'concessoes' => $pessoa->concessoes()->whereNull('revogada_em')->with('concedidaPor')->get()->keyBy('permissao_id'),
            'eventos' => $pessoa->eventosPermissao()->with(['permissao', 'autor'])->orderByDesc('ocorrido_em')->orderByDesc('id')->paginate(15),
        ]);
    }

    public function alterar(Request $request, int $funcionario): RedirectResponse
    {
        $pessoa = $this->funcionario($funcionario);
        $dados = $request->validate([
            'permissao_id' => ['required', 'integer', 'exists:permissoes,id'],
            'motivo' => ['required', 'string', 'max:2000'],
        ]);
        $permissao = Permissao::query()->findOrFail($dados['permissao_id']);
        $revogar = $request->routeIs('interno.permissoes.revogacoes.store');
        $alterou = $revogar
            ? $permissao->revogar($pessoa, $request->user(), $dados['motivo'])
            : $permissao->conceder($pessoa, $request->user(), $dados['motivo']);
        $mensagem = match (true) {
            ! $alterou => 'Nenhuma alteração: a permissão já estava '.($revogar ? 'revogada.' : 'concedida.'),
            $revogar => "Permissão “{$permissao->nome}” revogada.",
            default => "Permissão “{$permissao->nome}” concedida.",
        };

        return redirect()->route('interno.permissoes.show', $pessoa)->with('status', $mensagem);
    }

    private function funcionario(int $id): User
    {
        return User::query()->where('role', 'funcionario')->findOrFail($id);
    }
}
