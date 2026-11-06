<?php

namespace App\Http\Controllers;

use App\Models\AnexoItemAvaliacao;
use App\Models\EventoSolicitacao;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RetencaoController extends Controller
{
    public function index(): View
    {
        $limite = $this->limite();

        return view('retencao.index', [
            'anos' => $this->anos(),
            'limite' => $limite,
            'anexos' => $limite ? AnexoItemAvaliacao::query()->vencidos($limite)->with('item.avaliacao')->orderBy('id')->paginate(20) : null,
            'correcoes' => $limite ? EventoSolicitacao::query()->correcoesVencidas($limite)->orderBy('id')->paginate(20, ['*'], 'correcoes_page') : null,
            'descartados' => [
                'anexos' => AnexoItemAvaliacao::query()->whereNotNull('descartado_em')->count(),
                'correcoes' => EventoSolicitacao::query()->whereNotNull('valores_descartados_em')->count(),
            ],
        ]);
    }

    /**
     * Descarta o que venceu até o limite exibido na tela. Um limite posterior ao atual indica que o prazo aumentou depois de a
     * página ser aberta; nesse caso nada é descartado.
     */
    public function descartar(Request $request): RedirectResponse
    {
        $dados = $request->validate([
            'limite' => ['required', 'date'],
            'confirmacao' => ['accepted'],
        ], ['confirmacao.accepted' => 'Confirme que os arquivos e valores listados serão apagados sem possibilidade de recuperação.']);
        $atual = $this->limite();
        abort_unless($atual, 409, 'O prazo de retenção não está definido. Nada foi descartado.');
        $limite = CarbonImmutable::parse($dados['limite'])->utc();
        abort_if($limite->greaterThan($atual), 409, 'O prazo de retenção mudou. Recarregue a página e confira a lista antes de descartar.');
        $anexos = $correcoes = 0;
        AnexoItemAvaliacao::query()->vencidos($limite)->select('id')->lazyById()
            ->each(function (AnexoItemAvaliacao $anexo) use ($request, $limite, &$anexos): void {
                $anexos += (int) $anexo->descartar($request->user(), $limite);
            });
        EventoSolicitacao::query()->correcoesVencidas($limite)->select('id')->lazyById()
            ->each(function (EventoSolicitacao $evento) use ($request, $limite, &$correcoes): void {
                $correcoes += (int) $evento->descartarValores($request->user(), $limite);
            });

        return redirect()->route('interno.retencao.index')
            ->with('status', "Descarte concluído: {$anexos} arquivo(s) de exames e valores de {$correcoes} correção(ões) de pedidos.");
    }

    private function anos(): ?int
    {
        $anos = filter_var(config('retencao.anos'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $anos === false ? null : $anos;
    }

    private function limite(): ?CarbonImmutable
    {
        $anos = $this->anos();

        return $anos ? CarbonImmutable::now('UTC')->subYears($anos) : null;
    }
}
