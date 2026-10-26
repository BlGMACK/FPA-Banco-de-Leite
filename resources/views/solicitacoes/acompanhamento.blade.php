<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.acompanhamentos.index') }}">Voltar aos acompanhamentos</a>
        <h1 class="h3 mt-3">Acompanhamento administrativo</h1>
        <div class="alert alert-info">Cadastro concluído para acompanhamento. A aprovação para doar ainda depende das próximas etapas.</div>
        <h2 class="h5">{{ $pedido->interessada->nome_completo }}</h2>
        <p>Cadastro #{{ $pedido->interessada_id }}. Admitido em {{ $pedido->admitida_em->format('d/m/Y H:i') }} por {{ $pedido->admitidaPor->name }}.</p>
        <p><a href="{{ route('interno.solicitacoes.show', $pedido) }}">Ver pedido de origem #{{ $pedido->id }}, dados e histórico</a></p>
        <ul><li>Visita presencial: pendente de agendamento pela equipe.</li><li>Relatório da visita: pendente.</li><li>Documentos e exames: aguardar indicação após análise da visita.</li><li>Avaliação para doação: não concluída.</li></ul>
        <p>Esta tela apresenta somente o acompanhamento administrativo. O registro das próximas etapas será disponibilizado em entregas posteriores.</p>
    </div>
</x-app-layout>
