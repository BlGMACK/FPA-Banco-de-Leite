<?php

namespace App\Http\Controllers;

use App\Models\Agendamento;
use App\Models\Atendimento;
use App\Models\Avaliacao;
use App\Models\Permissao;
use App\Models\RelatorioVisita;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RelatorioVisitaController extends Controller
{
    public function index(Request $request): Response
    {
        $filtros = $request->validate(['estado' => ['nullable', 'in:rascunho,finalizado,em_correcao'],
            'responsavel' => ['nullable', 'integer', 'min:1']]);
        $escrita = Gate::allows(Permissao::REGISTRAR_RELATORIOS_VISITA);
        $query = RelatorioVisita::query()->select(['id', 'atendimento_id', 'estado', 'numero_versao_vigente', 'updated_at'])
            ->with(['atendimento.agendamento.solicitacao.interessada', 'atendimento.responsavel']);
        if (! $escrita) {
            $query->where('numero_versao_vigente', '>', 0);
        }
        if (! empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }
        if (! empty($filtros['responsavel'])) {
            $query->whereHas('atendimento', fn (Builder $q): Builder => $q->where('responsavel_user_id', $filtros['responsavel']));
        }
        $pendentes = $escrita ? Agendamento::query()->where('status', 'confirmado')->whereHas('reserva')
            ->whereHas('solicitacao', fn (Builder $q): Builder => $q->where('status', 'admitida')->whereNotNull('interessada_id')->whereDoesntHave('agendamentos.atendimento'))
            ->with(['solicitacao.interessada', 'responsavel'])->orderBy('inicio_previsto_em')->orderBy('id')
            ->paginate(15, ['*'], 'visitas_page')->appends($filtros) : null;

        return $this->pagina('relatorios.index', ['relatorios' => $query->orderByDesc('updated_at')->orderByDesc('id')->paginate(20)->appends($filtros),
            'pendentes' => $pendentes, 'filtros' => $filtros, 'escrita' => $escrita, 'responsaveis' => $this->responsaveis()]);
    }

    public function create(int $agendamento): Response
    {
        return $this->formularioVisita($agendamento);
    }

    public function store(Request $request, int $agendamento): RedirectResponse|Response
    {
        $dados = $request->only(['operacao_id', 'versao', 'inicio_efetivo_em', 'fim_efetivo_em', 'responsavel_user_id',
            'modalidade', 'local_atendimento', 'motivo_divergencia', 'realizacao_confirmada']);
        try {
            $evento = Atendimento::registrar($agendamento, $request->user(), $dados);
        } catch (ValidationException $exception) {
            return $this->formularioVisita($agendamento, $dados, $exception->errors(), 422);
        }

        return redirect()->route('interno.relatorios.show', $evento->relatorio_visita_id)->with('status', 'Visita registrada. Preencha o relatório.');
    }

    public function show(Request $request, int $relatorio): Response
    {
        $dados = $request->validate(['versao' => ['nullable', 'integer', 'min:1']]);

        return $this->detalhe($relatorio, isset($dados['versao']) ? (int) $dados['versao'] : null);
    }

    public function update(Request $request, int $relatorio): RedirectResponse|Response
    {
        Gate::authorize(Permissao::REGISTRAR_RELATORIOS_VISITA);
        $acao = match (true) {
            $request->routeIs('*.rascunho') => 'salvarRascunho',
            $request->routeIs('*.finalizar') => 'finalizar',
            default => 'iniciarCorrecao',
        };
        $dados = $request->only(['operacao_id', 'revisao', ...match ($acao) {
            'salvarRascunho' => ['texto'], 'iniciarCorrecao' => ['motivo'], default => [],
        }]);
        try {
            RelatorioVisita::query()->findOrFail($relatorio)->{$acao}($request->user(), $dados);
        } catch (ValidationException $exception) {
            return $this->detalhe($relatorio, null, $acao, $dados, $exception->errors(), 422);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return $this->detalhe($relatorio, null, $acao, $dados,
                ['conflito' => ['O relatório mudou ou a operação já foi utilizada. Copie seu texto antes de recarregar e conferir a versão atual.']], 409);
        }

        return redirect()->route('interno.relatorios.show', $relatorio)->with('status', match ($acao) {
            'salvarRascunho' => 'Rascunho salvo.', 'finalizar' => 'Versão finalizada. O histórico foi preservado.', default => 'Correção iniciada.',
        });
    }

    /** @param array<string, mixed> $entrada
     * @param  array<string, list<string>>  $falhas
     */
    private function formularioVisita(int $id, array $entrada = [], array $falhas = [], int $status = 200): Response
    {
        Gate::authorize(Permissao::REGISTRAR_RELATORIOS_VISITA);
        $agenda = Agendamento::query()->with(['solicitacao.interessada', 'responsavel'])->findOrFail($id);
        abort_unless($agenda->status === 'confirmado' && $agenda->reserva()->exists()
            && $agenda->solicitacao->status === 'admitida' && $agenda->solicitacao->interessada_id !== null
            && ! $agenda->solicitacao->agendamentos()->whereHas('atendimento')->exists(), 409, 'O compromisso não está disponível para realização.');

        return $this->pagina('relatorios.visita', ['agenda' => $agenda, 'entrada' => $this->escalares($entrada),
            'falhas' => $falhas, 'responsaveis' => $this->responsaveis($agenda->responsavel_user_id)], $status);
    }

    /** @param array<string, mixed> $entrada
     * @param  array<string, list<string>>  $falhas
     */
    private function detalhe(int $id, ?int $numero = null, ?string $formulario = null, array $entrada = [], array $falhas = [], int $status = 200): Response
    {
        Gate::authorize(Permissao::CONSULTAR_RELATORIOS_VISITA);
        $escrita = Gate::allows(Permissao::REGISTRAR_RELATORIOS_VISITA);
        $query = RelatorioVisita::query()->select(['id', 'atendimento_id', 'estado', 'numero_versao_vigente', 'revisao', 'criado_por_user_id', 'ultimo_editor_user_id', 'updated_at'])
            ->with(['atendimento.agendamento.solicitacao.interessada', 'atendimento.responsavel', 'criadoPor', 'ultimoEditor']);
        if (! $escrita) {
            $query->where('numero_versao_vigente', '>', 0);
        }
        $relatorio = $query->findOrFail($id);
        $numero ??= $relatorio->numero_versao_vigente;
        $versao = $numero > 0 ? RelatorioVisita::consultarVersao($id, $numero, auth()->user()) : null;
        $rascunho = $escrita ? RelatorioVisita::consultarRascunho($id, auth()->user()) : null;

        return $this->pagina('relatorios.show', ['relatorio' => $relatorio, 'versao' => $versao, 'rascunho' => $rascunho,
            'escrita' => $escrita, 'formulario' => $formulario,
            'analiseConcluida' => (bool) Avaliacao::query()->where('atendimento_id', $relatorio->atendimento_id)->first()?->concluida(), 'entrada' => $this->escalares($entrada), 'falhas' => $falhas,
            'finalizador' => $versao ? User::query()->findOrFail($versao['finalizado_por_user_id'])->name : null,
            'versoes' => $relatorio->versoes()->select(['id', 'relatorio_visita_id', 'numero', 'finalizado_por_user_id', 'finalizado_em'])
                ->with('finalizadoPor')->orderByDesc('numero')->paginate(15)->appends(['versao' => $numero])], $status);
    }

    /** Sem argumento, lista todos para filtros de histórico; com o responsável previsto, oferece só contas ativas e ele. */
    private function responsaveis(?int $previsto = null): Collection
    {
        return User::query()->whereIn('role', ['admin', 'funcionario'])
            ->when($previsto !== null, fn (Builder $query): Builder => $query->where(fn (Builder $ativos): Builder => $ativos->whereNull('desativado_em')->orWhere('id', $previsto)))
            ->orderBy('name')->get(['id', 'name']);
    }

    /** @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    private function escalares(array $entrada): array
    {
        return array_filter($entrada, fn (mixed $valor): bool => is_scalar($valor) || $valor === null);
    }

    /** @param array<string, mixed> $dados */
    private function pagina(string $view, array $dados, int $status = 200): Response
    {
        return response()->view($view, $dados, $status)->header('Cache-Control', 'private, no-store');
    }
}
