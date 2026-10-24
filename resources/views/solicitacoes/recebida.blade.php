<x-guest-layout>
    @if (session('pedido_recebido'))
        <h1 class="h3">Solicitação recebida</h1>
        <p role="status">Recebemos suas informações. A equipe verificará o pedido e entrará em contato pelo canal informado.</p>
        <p>Este recebimento não significa aprovação para doar nem confirmação de agendamento.</p>
    @else
        <h1 class="h3">Solicitação de cadastro</h1>
        <p>Para registrar seu interesse, preencha o formulário. O acompanhamento será feito pelo contato da equipe.</p>
    @endif
    <a href="{{ route('solicitacoes.create') }}" class="btn btn-outline-success">Abrir formulário</a>
</x-guest-layout>
