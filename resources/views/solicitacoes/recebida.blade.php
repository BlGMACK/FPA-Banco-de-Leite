<x-public-layout>
    <div class="blh-pagina">
        @if (session('pedido_recebido'))
            <h1>Solicitação recebida</h1>
            <p role="status">Recebemos suas informações. A equipe verificará o pedido e entrará em contato pelo canal informado.</p>
            <p>Este recebimento não significa aprovação para doar nem confirmação de agendamento.</p>
            <a href="{{ url('/') }}" class="btn btn-blh">Voltar ao início</a>
        @else
            <h1>Solicitação de cadastro</h1>
            <p>Para registrar seu interesse, preencha o formulário. O acompanhamento será feito pelo contato da equipe.</p>
            <a href="{{ route('solicitacoes.create') }}" class="btn btn-blh">Abrir formulário</a>
        @endif
    </div>
</x-public-layout>
