<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.solicitacoes.index') }}">Voltar à fila</a>
        <h1 class="h3 mt-3">Solicitação #{{ $pedido->id }}</h1>
        @if(session('status') === 'pedido-recebido')<div class="alert alert-success" role="status">Solicitação registrada.</div>@endif
        <p><strong>Situação:</strong> {{ ['enviada' => 'Enviada', 'em_analise' => 'Em análise', 'admitida' => 'Admitida para acompanhamento', 'arquivada' => 'Arquivada'][$pedido->status] }}. O cadastro administrativo não representa aprovação para doar.</p>
        <div class="card mb-4"><div class="card-body">
            <h2 class="h5">Dados informados</h2>
            <dl class="row mb-0">
                @foreach (['nome_completo' => 'Nome', 'cpf' => 'CPF', 'telefone_fixo' => 'Telefone fixo', 'telefone_celular' => 'Celular', 'email_contato' => 'E-mail', 'municipio' => 'Município', 'bairro' => 'Bairro', 'uf' => 'UF', 'cep' => 'CEP', 'logradouro' => 'Logradouro', 'numero' => 'Número', 'complemento' => 'Complemento'] as $campo => $rotulo)
                    <dt class="col-sm-3">{{ $rotulo }}</dt><dd class="col-sm-9">{{ $pedido->{$campo} ?: 'Não informado' }}</dd>
                @endforeach
                <dt class="col-sm-3">WhatsApp declarado</dt><dd class="col-sm-9">{{ $pedido->whatsapp_telefone === 'fixo' ? $pedido->telefone_fixo : $pedido->telefone_celular }}</dd>
                <dt class="col-sm-3">Canal de origem</dt><dd class="col-sm-9">{{ ['web' => 'Formulário público', 'telefone' => 'Telefone', 'presencial' => 'Presencial'][$pedido->canal_origem] }}</dd>
                <dt class="col-sm-3">Outros detalhes</dt><dd class="col-sm-9 text-break" style="white-space: pre-wrap">{{ $pedido->outros_detalhes ?: 'Não informado' }}</dd>
            </dl>
        </div></div>
        <h2 class="h5">Possíveis correspondências</h2>
        <p>Confirme com a interessada antes de tomar uma decisão. Pedidos arquivados não participam desta comparação.</p>
        @forelse ($correspondencias as $correspondencia)
            <div class="alert alert-warning">
                <strong>{{ $correspondencia['tipo'] === 'pedido' ? 'Pedido' : 'Cadastro concluído administrativamente' }} #{{ $correspondencia['registro']->id }}</strong>
                — {{ $correspondencia['registro']->nome_completo }}.
                <span>Dados coincidentes: {{ implode(', ', $correspondencia['campos']) }}.</span>
                @if($correspondencia['tipo'] === 'pedido')
                    <a href="{{ route('interno.solicitacoes.show', $correspondencia['registro']->id) }}">Ver pedido relacionado</a>
                @endif
            </div>
        @empty
            <p class="text-body-secondary">Nenhuma correspondência atual para este pedido.</p>
        @endforelse
    </div>
</x-app-layout>
