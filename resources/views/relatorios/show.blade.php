<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.relatorios.index') }}">Voltar aos relatórios</a>
        <h1 class="h3 mt-3">Relatório de visita #{{ $relatorio->id }}</h1>
        <h2 class="h5">{{ $relatorio->atendimento->agendamento->solicitacao->interessada->nome_completo }}</h2>
        <p>Visita em {{ $relatorio->atendimento->inicio_efetivo_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $relatorio->atendimento->responsavel->name }}.<br>A avaliação para doação continua em etapa própria.</p>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @if ($falhas)<div class="alert alert-danger" role="alert">@foreach ($falhas as $mensagens)@foreach ($mensagens as $mensagem)<p class="mb-0">{{ $mensagem }}</p>@endforeach @endforeach</div>@endif
        @if (isset($falhas['conflito']))<p><a href="{{ route('interno.relatorios.show', $relatorio) }}">Recarregar a versão atual (copie antes o texto ainda não salvo)</a></p>@endif
        @if ($versao)
            <section class="card mb-4"><div class="card-body">
                <h2 class="h4">Versão publicada {{ $versao['numero'] }}{{ $versao['numero'] === $relatorio->numero_versao_vigente ? ' — vigente' : ' — histórica' }}</h2>
                <p>Finalizada por {{ $finalizador }} em {{ $versao['finalizado_em']->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}.</p>
                @if ($versao['motivo_correcao'])<p>Motivo da correção: {{ $versao['motivo_correcao'] }}</p>@endif
                <div class="text-break" style="white-space: pre-wrap">{{ $versao['texto'] }}</div>
            </div></section>
        @else<p>Nenhuma versão publicada.</p>@endif
        @if ($escrita)
            <p class="small">Criado por {{ $relatorio->criadoPor->name }}. Última edição por {{ $relatorio->ultimoEditor->name }}.</p>
            @if ($rascunho['estado'] !== 'finalizado')
                <section class="card mb-4" x-data="{ alterado: {{ $formulario === 'salvarRascunho' ? 'true' : 'false' }} }"><div class="card-body">
                    <h2 class="h4">{{ $rascunho['estado'] === 'em_correcao' ? 'Correção em elaboração' : 'Rascunho' }}</h2>
                    @if ($rascunho['estado'] === 'em_correcao')<p>A versão {{ $rascunho['base_correcao_numero'] }} continua vigente até finalizar esta correção. Motivo: {{ $rascunho['motivo_correcao'] }}</p>@endif
                    <form method="POST" action="{{ route('interno.relatorios.rascunho', $relatorio) }}">@csrf
                        <input type="hidden" name="operacao_id" value="{{ $formulario === 'salvarRascunho' ? ($entrada['operacao_id'] ?? (string) Illuminate\Support\Str::uuid()) : (string) Illuminate\Support\Str::uuid() }}">
                        <input type="hidden" name="revisao" value="{{ $formulario === 'salvarRascunho' ? ($entrada['revisao'] ?? $rascunho['revisao']) : $rascunho['revisao'] }}">
                        <label for="texto" class="form-label">Relato da visita</label>
                        <textarea id="texto" name="texto" rows="14" maxlength="30000" class="form-control mb-3" x-on:input="alterado = true" @readonly(isset($falhas['conflito']))>{{ $formulario === 'salvarRascunho' ? ($entrada['texto'] ?? '') : $rascunho['texto_rascunho'] }}</textarea>
                        <button class="btn btn-primary" @disabled(isset($falhas['conflito']))>Salvar rascunho</button>
                    </form>
                    <p class="mt-3">Salve o rascunho antes de finalizar. A finalização publica o último texto salvo e preserva as versões anteriores.</p>
                    <form method="POST" action="{{ route('interno.relatorios.finalizar', $relatorio) }}">@csrf
                        <input type="hidden" name="operacao_id" value="{{ (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="revisao" value="{{ $rascunho['revisao'] }}">
                        <button class="btn btn-outline-success" x-bind:disabled="alterado || {{ isset($falhas['conflito']) ? 'true' : 'false' }}" @disabled(isset($falhas['conflito']))>Finalizar texto salvo</button>
                    </form>
                </div></section>
            @else
                <form method="POST" action="{{ route('interno.relatorios.correcao', $relatorio) }}" class="mb-4">@csrf
                    <h2 class="h4">Iniciar correção</h2><p>A correção parte da última versão vigente e não altera o histórico publicado.</p>
                    <input type="hidden" name="operacao_id" value="{{ $formulario === 'iniciarCorrecao' ? ($entrada['operacao_id'] ?? (string) Illuminate\Support\Str::uuid()) : (string) Illuminate\Support\Str::uuid() }}"><input type="hidden" name="revisao" value="{{ $rascunho['revisao'] }}">
                    <label class="form-label" for="motivo">Motivo da correção</label><textarea class="form-control mb-3" id="motivo" name="motivo" rows="3" maxlength="2000" required>{{ $formulario === 'iniciarCorrecao' ? ($entrada['motivo'] ?? '') : '' }}</textarea>
                    <button class="btn btn-primary" @disabled(isset($falhas['conflito']))>Abrir correção</button>
                </form>
            @endif
            @if ($formulario === 'salvarRascunho' && $rascunho['estado'] === 'finalizado')
                <label for="texto-conflito" class="form-label">Texto enviado e não salvo</label><textarea id="texto-conflito" class="form-control mb-4" rows="10" readonly>{{ $entrada['texto'] ?? '' }}</textarea>
            @endif
        @endif
        <h2 class="h4">Histórico de versões publicadas</h2>
        <ul class="list-group mb-3">@forelse ($versoes as $item)<li class="list-group-item"><a href="{{ route('interno.relatorios.show', ['relatorio' => $relatorio, 'versao' => $item->numero]) }}">Versão {{ $item->numero }}</a> · {{ $item->finalizadoPor->name }} · {{ $item->finalizado_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</li>@empty<li class="list-group-item">Nenhuma versão publicada.</li>@endforelse</ul>
        {{ $versoes->links() }}
    </div>
</x-app-layout>
