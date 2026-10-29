<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\ContatoSolicitacao;
use App\Models\Solicitacao;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AgendaController extends Controller
{
    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d', ...($request->filled('inicio') ? ['after_or_equal:inicio'] : [])],
            'modalidade' => ['nullable', 'in:domicilio,banco'],
            'situacao' => ['nullable', 'in:confirmado,substituido,cancelado,nao_realizado,realizado'],
            'responsavel' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = Agendamento::query()->with(['solicitacao.interessada', 'responsavel']);
        foreach (['modalidade' => 'modalidade', 'situacao' => 'status', 'responsavel' => 'responsavel_user_id'] as $filtro => $coluna) {
            if (! empty($filtros[$filtro])) {
                $query->where($coluna, $filtros[$filtro]);
            }
        }
        if (! empty($filtros['inicio'])) {
            $query->where('fim_previsto_em', '>', CarbonImmutable::parse($filtros['inicio'], 'America/Sao_Paulo')->startOfDay()->utc());
        }
        if (! empty($filtros['fim'])) {
            $query->where('inicio_previsto_em', '<', CarbonImmutable::parse($filtros['fim'], 'America/Sao_Paulo')->addDay()->startOfDay()->utc());
        }

        return view('agenda.index', [
            'agendamentos' => $query->orderBy('inicio_previsto_em')->orderBy('id')->paginate(25)->appends($filtros),
            'pendentes' => Solicitacao::query()->where('status', 'admitida')->whereNotNull('interessada_id')
                ->whereDoesntHave('reservaAgenda')->whereDoesntHave('agendamentos.atendimento')->with('interessada')->orderBy('admitida_em')->orderBy('id')
                ->paginate(25, ['*'], 'pendentes_page')->appends($filtros),
            'responsaveis' => User::query()->whereIn('role', ['admin', 'funcionario'])->orderBy('name')->get(['id', 'name']),
            'filtros' => $filtros,
        ]);
    }

    public function show(int $solicitacao): View
    {
        $pedido = Solicitacao::query()->where('status', 'admitida')->whereNotNull('interessada_id')
            ->with(['interessada', 'reservaAgenda.agendamento'])->findOrFail($solicitacao);
        $contatos = $pedido->contatos()->where('contexto', 'acompanhamento')->with('registradoPor')
            ->orderByDesc('ocorrido_em')->orderByDesc('contatos_solicitacao.id')->paginate(15, ['contatos_solicitacao.*'], 'contatos_page');

        return view('agenda.show', [
            'visitaRealizada' => $pedido->agendamentos()->whereHas('atendimento')->exists(),
            'pedido' => $pedido, 'contatos' => $contatos, 'atual' => $pedido->reservaAgenda?->agendamento,
            'confirmacoes' => $pedido->contatos()->where('contexto', 'acompanhamento')->where('resultado', 'realizado')
                ->orderByDesc('ocorrido_em')->get(['contatos_solicitacao.id', 'ocorrido_em', 'finalidade']),
            'agendamentos' => $pedido->agendamentos()->with(['responsavel', 'eventos.autor'])->orderByDesc('id')->paginate(15, ['*'], 'historico_page'),
            'responsaveis' => User::query()->whereIn('role', ['admin', 'funcionario'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function contato(Request $request, int $solicitacao): RedirectResponse
    {
        $dados = $request->only(['operacao_id', 'versao', 'canal', 'resultado', 'destinatario', 'finalidade', 'resumo', 'proxima_acao', 'ocorrido_em']);
        try {
            ContatoSolicitacao::registrarAcompanhamento($solicitacao, $request->user(), $dados);
        } catch (ValidationException $exception) {
            return $this->invalido($solicitacao, 'contato', $dados, $exception);
        }

        return redirect()->route('interno.agenda.acompanhamentos.show', $solicitacao)->with('status', 'Contato registrado. Nenhuma mensagem foi enviada automaticamente.');
    }

    public function store(Request $request, int $solicitacao): RedirectResponse
    {
        return $this->gravar($request, 'confirmacao', $solicitacao);
    }

    public function alterar(Request $request, int $agendamento): RedirectResponse
    {
        $acao = match (true) {
            $request->routeIs('*.reagendamentos.store') => 'reagendamento',
            $request->routeIs('*.cancelamento.store') => 'cancelamento',
            default => 'nao_realizacao',
        };

        return $this->gravar($request, $acao, $agendamento);
    }

    private function gravar(Request $request, string $acao, int $id): RedirectResponse
    {
        Gate::authorize('gerenciar-agenda');
        $agendamento = $acao === 'confirmacao' ? null : Agendamento::query()->findOrFail($id);
        $solicitacao = $agendamento?->solicitacao_id ?? $id;
        $dados = $request->only(['operacao_id', 'versao', 'motivo', 'contato_id', 'responsavel_user_id', 'modalidade',
            'local_atendimento', 'inicio_previsto_em', 'fim_previsto_em', 'combinado_confirmado']);
        try {
            match ($acao) {
                'confirmacao' => Agendamento::confirmar($id, $request->user(), $dados),
                'reagendamento' => $agendamento->reagendar($request->user(), $dados),
                'cancelamento' => $agendamento->cancelar($request->user(), $dados),
                'nao_realizacao' => $agendamento->registrarNaoRealizacao($request->user(), $dados),
            };
        } catch (ValidationException $exception) {
            return $this->invalido($solicitacao, $acao, $dados, $exception);
        }

        return redirect()->route('interno.agenda.acompanhamentos.show', $solicitacao)->with('status', 'Agenda atualizada. O histórico foi preservado.');
    }

    /** @param array<string, mixed> $dados */
    private function invalido(int $solicitacao, string $formulario, array $dados, ValidationException $exception): RedirectResponse
    {
        return redirect()->route('interno.agenda.acompanhamentos.show', $solicitacao)
            ->withErrors($exception->errors(), $formulario)->withInput(['formulario' => $formulario,
                'dados' => array_filter($dados, fn (mixed $valor): bool => is_scalar($valor) || $valor === null)]);
    }
}
