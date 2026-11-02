<?php

namespace App\Http\Controllers;

use App\Models\AnexoItemAvaliacao;
use App\Models\Avaliacao;
use App\Models\EventoItemAvaliacao;
use App\Models\ItemAvaliacao;
use App\Models\Permissao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExameController extends Controller
{
    public function index(): Response
    {
        $avaliacoes = $this->comExames(Avaliacao::query())->with(['atendimento.agendamento.solicitacao.interessada', 'atendimento.responsavel', 'itens.tipo'])
            ->orderByDesc('updated_at')->orderByDesc('id')->paginate(20);

        return $this->pagina('exames.index', ['avaliacoes' => $avaliacoes]);
    }

    public function show(int $avaliacao): Response
    {
        return $this->detalhe($avaliacao);
    }

    public function incluir(Request $request, int $avaliacao): RedirectResponse|Response
    {
        Gate::authorize(Permissao::REGISTRAR_EXAMES);
        $dados = $request->only(['operacao_id', 'nome_outro', 'detalhes_outro']);

        return $this->executar($avaliacao, 'outro', $dados,
            fn (): string => $this->mensagem(ItemAvaliacao::incluirOutro($this->comExames(Avaliacao::query())->findOrFail($avaliacao), $request->user(), $dados)));
    }

    public function registrar(Request $request, int $item): RedirectResponse|Response
    {
        Gate::authorize(Permissao::REGISTRAR_EXAMES);
        $registro = ItemAvaliacao::query()->findOrFail($item);
        $dados = $request->only(['operacao_id', 'versao', 'acao', 'motivo', 'realizado_em', 'numero_documento']);

        return $this->executar($registro->avaliacao_id, 'item-'.$registro->id, $dados, fn (): string => $this->mensagem($registro->registrar($request->user(), $dados)));
    }

    public function anexar(Request $request, int $item): RedirectResponse|Response
    {
        Gate::authorize(Permissao::REGISTRAR_EXAMES);
        $registro = ItemAvaliacao::query()->findOrFail($item);
        $operacao = $request->input('operacao_id');

        return $this->executar($registro->avaliacao_id, 'item-'.$registro->id, ['operacao_id' => $operacao, 'acao' => 'anexo'],
            function () use ($registro, $request, $operacao): string {
                $anexo = $registro->anexar($request->user(), ['operacao_id' => $operacao, 'arquivo' => $request->file('arquivo')]);

                return 'Arquivo “'.$anexo->nome_original.'” anexado a “'.$registro->nome().'”.';
            });
    }

    /** Entrega o arquivo só a quem consulta exames; nunca há URL pública para o disco de anexos. */
    public function arquivo(Request $request, int $anexo): StreamedResponse
    {
        Gate::authorize(Permissao::CONSULTAR_EXAMES);
        $registro = AnexoItemAvaliacao::query()->findOrFail($anexo);
        abort_unless(Storage::disk('anexos')->exists($registro->caminho), 404, 'Arquivo não encontrado no armazenamento.');

        return Storage::disk('anexos')->response($registro->caminho, $registro->nome_original, [
            'Content-Type' => $registro->tipo_conteudo, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ], $request->boolean('baixar') ? 'attachment' : 'inline');
    }

    private function mensagem(EventoItemAvaliacao $evento): string
    {
        return 'Registrado: '.mb_strtolower(EventoItemAvaliacao::ACOES[$evento->acao]).' de “'.$evento->item->nome().'”. O histórico foi preservado.';
    }

    /** @param array<string, mixed> $dados */
    private function executar(int $avaliacao, string $formulario, array $dados, callable $operacao): RedirectResponse|Response
    {
        try {
            $status = $operacao();
        } catch (ValidationException $exception) {
            return $this->detalhe($avaliacao, $formulario, $dados, $exception->errors(), 422);
        } catch (HttpException $exception) {
            if ($exception->getStatusCode() !== 409) {
                throw $exception;
            }

            return $this->detalhe($avaliacao, $formulario, $dados, ['conflito' => [$exception->getMessage()]], 409);
        }

        return redirect()->route('interno.exames.show', $avaliacao)->with('status', $status);
    }

    /** @param array<string, mixed> $entrada
     * @param  array<string, list<string>>  $falhas
     */
    private function detalhe(int $id, ?string $formulario = null, array $entrada = [], array $falhas = [], int $status = 200): Response
    {
        Gate::authorize(Permissao::CONSULTAR_EXAMES);
        $avaliacao = $this->comExames(Avaliacao::query())->with(['atendimento.agendamento.solicitacao.interessada', 'atendimento.responsavel'])->findOrFail($id);
        $itens = $avaliacao->itens()->with(['tipo', 'anexos' => fn ($q) => $q->with('enviadoPor:id,name')->orderByDesc('enviado_em')->orderByDesc('id')])->withExists(['eventos as ja_recebido' => fn (Builder $q): Builder => $q->where('acao', 'recebimento')])
            ->leftJoin('tipos_exame', 'tipos_exame.id', '=', 'itens_avaliacao.tipo_exame_id')->select('itens_avaliacao.*')
            ->orderByRaw('itens_avaliacao.tipo_exame_id is null')->orderBy('tipos_exame.ordem')->orderBy('itens_avaliacao.id')->get();

        return $this->pagina('exames.show', ['avaliacao' => $avaliacao, 'itens' => $itens,
            'escrita' => Gate::allows(Permissao::REGISTRAR_EXAMES) && $avaliacao->estado === 'liberada_exames',
            'leituraAvaliacao' => Gate::allows(Permissao::CONSULTAR_AVALIACOES),
            'formulario' => $formulario, 'entrada' => array_filter($entrada, fn (mixed $valor): bool => is_scalar($valor) || $valor === null),
            'falhas' => $falhas,
            'eventos' => EventoItemAvaliacao::query()->whereHas('item', fn (Builder $q): Builder => $q->where('avaliacao_id', $avaliacao->id))
                ->with(['item.tipo', 'autor:id,name'])->orderByDesc('ocorrido_em')->orderByDesc('id')->paginate(20)], $status);
    }

    /** Avaliações com etapa de exames: liberadas agora ou que já tiveram itens. */
    private function comExames(Builder $query): Builder
    {
        return $query->where(fn (Builder $q): Builder => $q->where('estado', 'liberada_exames')->orWhereHas('itens'));
    }

    /** @param array<string, mixed> $dados */
    private function pagina(string $view, array $dados, int $status = 200): Response
    {
        return response()->view($view, $dados, $status)->header('Cache-Control', 'private, no-store');
    }
}
