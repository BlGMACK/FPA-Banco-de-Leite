<?php

namespace App\Http\Controllers;

use App\Http\Requests\AtendimentoSolicitacaoRequest;
use App\Http\Requests\CorrecaoSolicitacaoRequest;
use App\Models\ContatoSolicitacao;
use App\Models\ResolucaoSolicitacao;
use App\Models\Solicitacao;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AtendimentoSolicitacaoController extends Controller
{
    public function analise(AtendimentoSolicitacaoRequest $request, int $solicitacao): RedirectResponse
    {
        DB::transaction(function () use ($request, $solicitacao): void {
            $pedidos = Solicitacao::bloquearPedidos($request->validated('pedidos'), $solicitacao);
            abort_unless($pedidos->count() === 1, 422);
            Gate::authorize('atender-solicitacoes');
            $pedido = $pedidos[$solicitacao];
            abort_unless($pedido->status === 'enviada', 409, 'O pedido mudou. Recarregue a página.');
            $pedido->status = 'em_analise';
            $pedido->versao++;
            $pedido->save();
            $pedido->registrarEvento('inicio_analise', $request->user(), 'enviada');
        });

        return redirect()->route('interno.solicitacoes.show', $solicitacao)->with('status', 'analise-iniciada');
    }

    public function contato(AtendimentoSolicitacaoRequest $request, int $solicitacao): RedirectResponse
    {
        $dados = $request->validated();
        if ($dados['resultado'] !== 'realizado' && ($request->boolean('identidade_conferida') || $request->boolean('duvida_esclarecida'))) {
            throw ValidationException::withMessages(['resultado' => 'Somente contato realizado permite conferir identidade ou esclarecer dúvidas.'])->errorBag('contato');
        }
        DB::transaction(function () use ($request, $solicitacao, $dados): void {
            $pedidos = Solicitacao::bloquearPedidos($dados['pedidos'], $solicitacao);
            Gate::authorize('atender-solicitacoes');
            $contato = new ContatoSolicitacao;
            $contato->forceFill($request->safe()->only([
                'canal', 'resultado', 'destinatario', 'finalidade', 'resumo', 'proxima_acao', 'ocorrido_em',
            ]) + [
                'registrado_por_user_id' => $request->user()->id,
                'identidade_conferida' => $request->boolean('identidade_conferida'),
                'duvida_esclarecida' => $request->boolean('duvida_esclarecida'),
            ])->save();
            $contato->solicitacoes()->attach($pedidos->keys());
            foreach ($pedidos as $pedido) {
                $pedido->versao++;
                $pedido->save();
            }
        });

        return redirect()->route('interno.solicitacoes.show', $solicitacao)->with('status', 'contato-registrado');
    }

    public function decisao(AtendimentoSolicitacaoRequest $request, int $solicitacao): RedirectResponse
    {
        $dados = $request->validated();
        $permitidas = $request->routeIs('*.conferencias.store') ? ['pessoas_distintas', 'aguardando_esclarecimento']
            : ($request->routeIs('*.admissao.store') ? ['admitir'] : ['arquivar_selecionados', 'admitir_principal']);
        abort_unless(in_array($dados['acao'], $permitidas, true), 422);
        try {
            $resolucao = ResolucaoSolicitacao::executar($dados, $solicitacao, $request->user());
        } catch (UniqueConstraintViolationException $exception) {
            if (! ResolucaoSolicitacao::query()->where('operacao_id', $dados['operacao_id'])->exists()) {
                throw $exception;
            }
            abort(409, 'Operação já utilizada. Recarregue a página.');
        }
        if ($resolucao->principal_id) {
            return redirect()->route('interno.acompanhamentos.show', $resolucao->principal_id)->with('status', 'cadastro-admitido');
        }
        if ($resolucao->tipo === 'arquivar_selecionados') {
            return redirect()->route('interno.solicitacoes.index')->with('status', 'pedidos-arquivados');
        }

        return redirect()->route('interno.solicitacoes.show', $solicitacao)->with('status', 'conferencia-registrada');
    }

    public function correcao(CorrecaoSolicitacaoRequest $request, int $solicitacao): RedirectResponse
    {
        $evento = Solicitacao::corrigirDados($solicitacao, $request->user(), $request->validated());

        return redirect()->route($evento->estado_novo === 'admitida' ? 'interno.acompanhamentos.show' : 'interno.solicitacoes.show', $solicitacao)
            ->with('status', 'dados-corrigidos');
    }

    public function index(): View
    {
        return view('solicitacoes.acompanhamentos', ['pedidos' => Solicitacao::query()->where('status', 'admitida')
            ->with('interessada')->orderByDesc('admitida_em')->orderByDesc('id')->paginate(25)]);
    }

    public function show(int $solicitacao): View
    {
        return view('solicitacoes.acompanhamento', ['pedido' => Solicitacao::query()->where('status', 'admitida')
            ->with(['interessada', 'admitidaPor'])->findOrFail($solicitacao)]);
    }
}
