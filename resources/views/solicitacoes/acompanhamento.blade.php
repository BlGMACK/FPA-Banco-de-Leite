<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.acompanhamentos.index') }}">Voltar aos acompanhamentos</a>
        <h1 class="h3 mt-3">Acompanhamento administrativo</h1>
        <div class="alert alert-info">Cadastro concluído para acompanhamento. A aprovação para doar ainda depende das próximas etapas.</div>
        <h2 class="h5">{{ $pedido->interessada->nome_completo }}</h2>
        <p>Cadastro #{{ $pedido->interessada_id }}. Admitido em {{ $pedido->admitida_em->format('d/m/Y H:i') }} por {{ $pedido->admitidaPor->name }}.</p>
        <p><a href="{{ route('interno.solicitacoes.show', $pedido) }}">Ver pedido de origem #{{ $pedido->id }}, dados e histórico</a></p>
        @can('gerenciar-agenda')
            <p><a class="btn btn-primary" href="{{ route('interno.agenda.acompanhamentos.show', $pedido) }}">Abrir agenda e contatos da visita</a></p>
        @endcan
        <ul><li>Visita presencial: consultar programação com a equipe de agenda.</li><li>Relatório da visita: consultar a equipe autorizada.</li><li>Documentos e exames: aguardar indicação após análise da visita.</li><li>Avaliação para doação: não concluída.</li></ul>
        <p>Esta tela apresenta somente o acompanhamento administrativo. O registro das próximas etapas será disponibilizado em entregas posteriores.</p>
    </div>
</x-app-layout>
