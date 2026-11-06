<?php

namespace App\Http\Controllers;

use App\Models\Doadora;
use App\Models\EventoDoadora;
use App\Models\Permissao;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DoadoraController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate(['estado' => ['nullable', 'in:'.implode(',', array_keys(Doadora::ESTADOS))]]);
        $query = Doadora::query()->with('interessada');
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        return $this->pagina('doadoras.index', ['doadoras' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->appends($filtros),
            'filtros' => $filtros]);
    }

    public function show(int $doadora): Response
    {
        return $this->detalhe($doadora);
    }

    public function registrar(Request $request, int $doadora): RedirectResponse|Response
    {
        Gate::authorize(Permissao::AVALIAR_DOADORAS);
        $dados = $request->only(['operacao_id', 'versao', 'acao', 'motivo', 'condicao', 'valida_ate', 'confirmacao_definitiva']);
        try {
            $evento = Doadora::query()->findOrFail($doadora)->registrar($request->user(), $dados);
        } catch (ValidationException $exception) {
            return $this->detalhe($doadora, $dados, $exception->errors(), 422);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return $this->detalhe($doadora, $dados, ['conflito' => [$exception->getMessage()]], 409);
        }

        return redirect()->route('interno.doadoras.show', $doadora)
            ->with('status', 'Registrado: '.mb_strtolower(EventoDoadora::ACOES[$evento->acao]).'. O histórico foi preservado.');
    }

    /** @param array<string, mixed> $entrada
     * @param  array<string, list<string>>  $falhas
     */
    private function detalhe(int $id, array $entrada = [], array $falhas = [], int $status = 200): Response
    {
        Gate::authorize(Permissao::CONSULTAR_AVALIACOES);
        $doadora = Doadora::query()->with(['interessada', 'avaliacao.atendimento.responsavel', 'decisao.autor:id,name'])->findOrFail($id);

        return $this->pagina('doadoras.show', ['doadora' => $doadora,
            'escrita' => Gate::allows(Permissao::AVALIAR_DOADORAS) && $doadora->vigente(),
            'entrada' => array_filter($entrada, fn (mixed $valor): bool => is_scalar($valor) || $valor === null), 'falhas' => $falhas,
            'eventos' => $doadora->eventos()->with('autor:id,name')->orderByDesc('ocorrido_em')->orderByDesc('id')->paginate(20)], $status);
    }

    /** @param array<string, mixed> $dados */
    private function pagina(string $view, array $dados, int $status = 200): Response
    {
        return response()->view($view, $dados, $status)->header('Cache-Control', 'private, no-store');
    }
}
