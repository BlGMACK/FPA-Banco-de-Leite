<x-app-layout>
    <div class="container py-4">
        <h1 class="h3">Retenção de dados</h1>
        <p>Os arquivos dos exames são guardados pelo prazo de retenção, contado da decisão de habilitação. Os valores anteriores e novos das correções de pedidos seguem o mesmo prazo, contado da correção. Vencido o prazo, o descarte só acontece quando um administrador o confirma aqui; nada é apagado automaticamente.</p>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif

        @if ($anos === null)
            <div class="alert alert-info" role="status">O prazo de retenção não está definido, por isso nada fica disponível para descarte. A definição cabe à equipe responsável pelo serviço; depois, quem administra o servidor configura <code>RETENCAO_ANOS</code>.</div>
        @else
            <p>Prazo configurado: <strong>{{ $anos }} {{ $anos === 1 ? 'ano' : 'anos' }}</strong>. Venceu o que ocorreu até {{ $limite->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}.</p>
        @endif
        <p class="small text-body-secondary">Já descartados: {{ $descartados['anexos'] }} arquivo(s) de exames e valores de {{ $descartados['correcoes'] }} correção(ões).</p>

        @if ($anos !== null)
            <h2 class="h5 mt-4">Arquivos de exames vencidos</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th scope="col">Arquivo</th><th scope="col">Avaliação</th><th scope="col">Enviado em</th></tr></thead>
                    <tbody>
                        @forelse ($anexos as $anexo)
                            <tr>
                                <td class="text-break">{{ $anexo->nome_original }} · {{ $anexo->tamanhoLegivel() }}</td>
                                <td><a href="{{ route('interno.exames.show', $anexo->item->avaliacao_id) }}">#{{ $anexo->item->avaliacao_id }}</a></td>
                                <td>{{ $anexo->enviado_em->setTimezone('America/Sao_Paulo')->format('d/m/Y') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Nenhum arquivo vencido.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $anexos->links() }}

            <h2 class="h5 mt-4">Correções de pedidos vencidas</h2>
            <div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th scope="col">Pedido</th><th scope="col">Corrigido em</th><th scope="col">Campos</th></tr></thead>
                    <tbody>
                        @forelse ($correcoes as $correcao)
                            <tr>
                                <td><a href="{{ route('interno.solicitacoes.show', $correcao->solicitacao_id) }}">#{{ $correcao->solicitacao_id }}</a></td>
                                <td>{{ $correcao->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y') }}</td>
                                <td class="text-break">{{ implode(', ', $correcao->camposCorrigidos()) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3">Nenhuma correção vencida.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ $correcoes->links() }}

            @if ($anexos->total() > 0 || $correcoes->total() > 0)
                <form method="POST" action="{{ route('interno.retencao.descarte.store') }}" class="border border-danger rounded p-3 mt-4">
                    @csrf
                    <input type="hidden" name="limite" value="{{ $limite->toIso8601String() }}">
                    <p class="mb-2">Serão apagados {{ $anexos->total() }} arquivo(s) e os valores de {{ $correcoes->total() }} correção(ões). Os registros continuam no histórico com nome do arquivo, campos corrigidos, autor, motivo e datas, marcados com a data do descarte e o seu nome.</p>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="confirmacao" value="1" id="confirmacao" required>
                        <label class="form-check-label" for="confirmacao">Confirmo que os arquivos e valores listados serão apagados sem possibilidade de recuperação.</label>
                    </div>
                    <button type="submit" class="btn btn-danger">Descartar o que venceu</button>
                </form>
            @endif
        @endif
    </div>
</x-app-layout>
