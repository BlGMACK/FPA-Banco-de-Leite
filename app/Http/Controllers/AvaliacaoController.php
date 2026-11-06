<?php

namespace App\Http\Controllers;

use App\Models\Avaliacao;
use App\Models\Permissao;
use App\Models\RelatorioVisita;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AvaliacaoController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate(['estado' => ['nullable', 'in:'.implode(',', array_keys(Avaliacao::ESTADOS))]]);
        $query = Avaliacao::query()->with(['atendimento.agendamento.solicitacao.interessada', 'atendimento.responsavel', 'analisadaPor:id,name']);
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $this->pagina('avaliacoes.index', ['avaliacoes' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->appends($filtros),
            'filtros' => $filtros]);
    }

    public function show(int $avaliacao): Response
    {
        return $this->detalhe($avaliacao);
    }

    public function iniciarAnalise(Request $request, int $avaliacao): RedirectResponse|Response
    {
        Gate::authorize(Permissao::AVALIAR_DOADORAS);
        try {
            Avaliacao::query()->findOrFail($avaliacao)->iniciarAnalise($request->user());
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return $this->detalhe($avaliacao, [], ['conflito' => [$exception->getMessage()]], 409);
        }

        return redirect()->route('interno.avaliacoes.show', $avaliacao);
    }

    public function encerrarAnalise(Request $request, int $avaliacao): RedirectResponse
    {
        Gate::authorize(Permissao::AVALIAR_DOADORAS);
        Avaliacao::query()->findOrFail($avaliacao)->encerrarAnalise($request->user());

        return redirect()->route('interno.avaliacoes.show', $avaliacao)->with('status', 'Análise encerrada sem decisão. A avaliação ficou livre para outro profissional.');
    }

    public function store(Request $request, int $avaliacao): RedirectResponse|Response
    {
        Gate::authorize(Permissao::AVALIAR_DOADORAS);
        $dados = $request->only(['operacao_id', 'versao', 'resultado', 'motivo', 'confirmacao_definitiva']);
        try {
            Avaliacao::query()->findOrFail($avaliacao)->decidir($request->user(), $dados);
        } catch (ValidationException $exception) {
            return $this->detalhe($avaliacao, $dados, $exception->errors(), 422);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return $this->detalhe($avaliacao, $dados, ['conflito' => [$exception->getMessage()]], 409);
        }

        return redirect()->route('interno.avaliacoes.show', $avaliacao)->with('status', 'Decisão registrada. O histórico foi preservado.');
    }

    public function habilitar(Request $request, int $avaliacao): RedirectResponse|Response
    {
        Gate::authorize(Permissao::AVALIAR_DOADORAS);
        $dados = $request->only(['operacao_id', 'versao', 'resultado', 'possui_plano_saude', 'motivo', 'condicao', 'valida_ate', 'confirmacao_definitiva']);
        try {
            Avaliacao::query()->findOrFail($avaliacao)->habilitar($request->user(), $dados);
        } catch (ValidationException $exception) {
            return $this->detalhe($avaliacao, $dados, $exception->errors(), 422);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return $this->detalhe($avaliacao, $dados, ['conflito' => [$exception->getMessage()]], 409);
        }

        return redirect()->route('interno.avaliacoes.show', $avaliacao)->with('status', 'Decisão de habilitação registrada. O histórico foi preservado.');
    }

    /** @param array<string, mixed> $entrada
     * @param  array<string, list<string>>  $falhas
     */
    private function detalhe(int $id, array $entrada = [], array $falhas = [], int $status = 200): Response
    {
        Gate::authorize(Permissao::CONSULTAR_AVALIACOES);
        $avaliacao = Avaliacao::query()->with(['atendimento.agendamento.solicitacao.interessada', 'atendimento.agendamento.solicitacao.reservaAgenda',
            'atendimento.responsavel', 'analisadaPor:id,name', 'itens', 'doadora'])->findOrFail($id);
        $pedido = $avaliacao->atendimento->agendamento->solicitacao;
        $relatorios = $avaliacao->relatorios()->with('atendimento.responsavel')->get(['id', 'atendimento_id', 'estado', 'numero_versao_vigente']);
        $relatorio = $relatorios->last();
        $publicados = $relatorios->every(fn (RelatorioVisita $item): bool => $item->estado === 'finalizado' && $item->numero_versao_vigente > 0);
        $usuario = request()->user();
        $aberta = Gate::allows(Permissao::AVALIAR_DOADORAS) && ! $avaliacao->concluida() && $publicados && $pedido->reservaAgenda === null;
        // Com a reserva vencida e livre, um envio recusado por validação continua editável para não perder o texto.
        $podeDecidir = $aberta && ($avaliacao->reservadaPara($usuario) || ($entrada !== [] && ! isset($falhas['conflito']) && ! $avaliacao->reservadaPorOutro($usuario)));

        return $this->pagina('avaliacoes.show', ['avaliacao' => $avaliacao, 'relatorio' => $relatorio, 'relatorios' => $relatorios,
            'publicados' => $publicados, 'visitaMarcada' => $pedido->reservaAgenda !== null,
            'agendarComplementar' => $avaliacao->estado === 'em_aguardo' && $pedido->reservaAgenda === null
                && Gate::allows(Permissao::GERENCIAR_AGENDA) && $pedido->aceitaVisita(),
            'leituraRelatorio' => Gate::allows(Permissao::CONSULTAR_RELATORIOS_VISITA),
            'podeDecidir' => $podeDecidir, 'podeIniciar' => $aberta && ! $podeDecidir && ! $avaliacao->reservadaPorOutro($usuario),
            'reservadaPorOutro' => ! $avaliacao->concluida() && $avaliacao->reservadaPorOutro($usuario),
            'etapaExames' => $avaliacao->estado === 'liberada_exames', 'exigenciasCumpridas' => Avaliacao::exigenciasCumpridas($avaliacao->itens),
            'podeHabilitar' => $avaliacao->estado === 'liberada_exames' && Gate::allows(Permissao::AVALIAR_DOADORAS)
                && Avaliacao::exigenciasCumpridas($avaliacao->itens),
            'entrada' => array_filter($entrada, fn (mixed $valor): bool => is_scalar($valor) || $valor === null), 'falhas' => $falhas,
            'decisoes' => $avaliacao->decisoes()->with(['autor:id,name', 'versaoRelatorio:id,relatorio_visita_id,numero'])
                ->orderByDesc('decidido_em')->orderByDesc('id')->paginate(15)], $status);
    }

    /** @param array<string, mixed> $dados */
    private function pagina(string $view, array $dados, int $status = 200): Response
    {
        return response()->view($view, $dados, $status)->header('Cache-Control', 'private, no-store');
    }
}
