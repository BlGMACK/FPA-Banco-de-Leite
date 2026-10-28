<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFuncionarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class FuncionarioController extends Controller
{
    public function create(): View
    {
        return view('funcionarios.create');
    }

    public function store(StoreFuncionarioRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            Gate::authorize('criar-funcionarios');
            $user = new User($request->safe()->only(['name', 'email']));
            $user->forceFill(['role' => 'funcionario', 'password' => Str::random(64),
                'criado_por_user_id' => $request->user()->id])->save();
        });

        return redirect()->route('interno.funcionarios.create')->with('status', 'Funcionário cadastrado sem permissões adicionais. Oriente a pessoa a definir sua senha pelo link “Esqueceu sua senha?” na tela de acesso.');
    }
}
