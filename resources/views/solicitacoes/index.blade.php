<x-app-layout>
    <div class="container py-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h1 class="h3 mb-0">Solicitações de cadastro</h1>
            <a class="btn btn-success" href="{{ route('interno.solicitacoes.create') }}">Registrar solicitação</a>
        </div>
        <p>Possíveis correspondências são indícios para conferência pela equipe, não confirmação de que se trata da mesma pessoa.</p>
        <form method="GET" action="{{ route('interno.solicitacoes.index') }}" class="row g-3 align-items-end mb-4">
            <div class="col-md-3">
                <label class="form-label" for="situacao">Situação</label>
                <select class="form-select" id="situacao" name="situacao">
                    @foreach (['ativos' => 'Ativos', 'arquivados' => 'Arquivados', 'todos' => 'Todos'] as $valor => $rotulo)
                        <option value="{{ $valor }}" @selected($filtros['situacao'] === $valor)>{{ $rotulo }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="ordem">Ordem de criação</label>
                <select class="form-select" id="ordem" name="ordem">
                    <option value="antigos" @selected($filtros['ordem'] === 'antigos')>Mais antigos primeiro</option>
                    <option value="recentes" @selected($filtros['ordem'] === 'recentes')>Mais recentes primeiro</option>
                </select>
            </div>
            <div class="col-md-4"><div class="form-check">
                <input class="form-check-input" type="checkbox" name="com_correspondencias" id="com_correspondencias" value="1" @checked($filtros['com_correspondencias'])>
                <label class="form-check-label" for="com_correspondencias">Somente com possíveis correspondências</label>
            </div></div>
            <div class="col-md-2"><button type="submit" class="btn btn-primary">Filtrar</button></div>
        </form>
        @if ($errors->any())<div class="alert alert-danger" role="alert">Selecione filtros válidos.</div>@endif
        <p class="small">{{ $pedidos->total() }} pedido(s) nesta seleção.</p>
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <caption>Pedidos em ordem de criação. O destaque indica dados que precisam de conferência.</caption>
                <thead><tr><th scope="col">Pedido</th><th scope="col">Nome</th><th scope="col">Recebido em</th><th scope="col">Situação</th><th scope="col">Conferência</th></tr></thead>
                <tbody>
                @forelse ($pedidos as $pedido)
                    @php($alerta = $pedido->correspondencia_pedido || $pedido->correspondencia_cadastro)
                    <tr class="{{ $alerta ? 'table-warning' : '' }}">
                        <td><a href="{{ route('interno.solicitacoes.show', $pedido) }}">#{{ $pedido->id }}</a></td>
                        <td>{{ $pedido->nome_completo }}</td>
                        <td>{{ $pedido->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ ['enviada' => 'Enviada', 'em_analise' => 'Em análise', 'admitida' => 'Admitida para acompanhamento', 'arquivada' => 'Arquivada'][$pedido->status] }}</td>
                        <td>@if($alerta)<span class="badge text-bg-warning">Possíveis correspondências</span>@else<span class="text-body-secondary">Sem alerta atual</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5">Nenhuma solicitação nesta seleção.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        {{ $pedidos->links() }}
    </div>
</x-app-layout>
