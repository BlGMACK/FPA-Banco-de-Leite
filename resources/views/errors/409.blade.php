<x-guest-layout>
    <div class="container py-5">
        <h1 class="h3">Os dados mudaram</h1>
        <p>{{ $exception->getMessage() ?: 'A operação entrou em conflito com uma alteração anterior.' }}</p>
        @if (request()->routeIs('interno.agenda.*'))
            <p>Reabra o acompanhamento e confira os dados antes de tentar novamente. Nenhuma repetição automática foi realizada.</p>
        @else
            <p>Reabra o formulário e confira os dados antes de tentar novamente.</p>
            <a href="{{ url('/') }}">Voltar ao início</a>
        @endif
        @can('gerenciar-agenda')<a class="btn btn-primary" href="{{ route('interno.agenda.index') }}">Voltar à agenda</a>@endcan
        @can('atender-solicitacoes')<a class="btn btn-outline-secondary" href="{{ route('interno.acompanhamentos.index') }}">Acompanhamentos</a>@endcan
    </div>
</x-guest-layout>
