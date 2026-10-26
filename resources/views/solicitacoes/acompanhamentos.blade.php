<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.solicitacoes.index') }}">Voltar à fila</a>
        <h1 class="h3 mt-3">Cadastros em acompanhamento</h1>
        <p>Admissão administrativa não representa aprovação para doar.</p>
        @forelse($pedidos as $pedido)
            <div class="card mb-3"><div class="card-body"><a href="{{ route('interno.acompanhamentos.show', $pedido) }}">{{ $pedido->interessada->nome_completo }} — pedido #{{ $pedido->id }}</a><p class="mb-0">Visita pendente</p></div></div>
        @empty<p>Nenhum cadastro admitido.</p>@endforelse
        {{ $pedidos->links() }}
    </div>
</x-app-layout>
