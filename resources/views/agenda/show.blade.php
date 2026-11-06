<x-app-layout>
    <div class="container py-4">
        <a href="{{ route('interno.agenda.index') }}">Voltar à agenda</a>
        <h1 class="h3 mt-3">Visita — {{ $pedido->interessada->nome_completo }}</h1>
        <p>Acompanhamento #{{ $pedido->id }} · cadastro #{{ $pedido->interessada_id }}. A admissão ainda não representa aprovação para doar.</p>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @can('atender-solicitacoes')<p><a href="{{ route('interno.acompanhamentos.show', $pedido) }}">Ver acompanhamento administrativo</a></p>@endcan
        <div class="card mb-4"><div class="card-body">
            <h2 class="h5">Dados para contato e visita</h2>
            <p>Fixo: {{ $pedido->interessada->telefone_fixo ?: 'Não informado' }} · Celular: {{ $pedido->interessada->telefone_celular ?: 'Não informado' }}<br>WhatsApp indicado: {{ $pedido->interessada->whatsapp_telefone === 'fixo' ? $pedido->interessada->telefone_fixo : $pedido->interessada->telefone_celular }}<br>E-mail: {{ $pedido->interessada->email_contato }}</p>
            <p class="mb-0">{{ $pedido->interessada->logradouro }}, {{ $pedido->interessada->numero }} {{ $pedido->interessada->complemento }} — {{ $pedido->interessada->bairro }}, {{ $pedido->interessada->municipio }}/{{ $pedido->interessada->uf }} — CEP {{ $pedido->interessada->cep }}</p>
        </div></div>
        <div class="alert alert-info">Horários de Marília (America/Sao_Paulo). Horários sobrepostos do mesmo funcionário são bloqueados; considere o deslocamento manualmente. Use somente informações operacionais, sem parecer clínico.</div>
        <section class="card mb-4"><div class="card-body"><h2 class="h4">Registrar contato</h2><p>Registre o contato feito pela equipe. Este formulário não envia mensagens.</p>
            <form method="POST" action="{{ route('interno.agenda.contatos.store', $pedido) }}">@csrf
                @include('agenda.formulario', ['formulario' => 'contato', 'versao' => $pedido->versao])
                <button class="btn btn-primary">Registrar contato</button>
            </form>
        </div></section>
        @if (! $aceitaVisita)
            <p class="alert alert-info">Visita realizada. O acompanhamento não está aguardando novo agendamento.</p>
        @else
        <section class="card mb-4"><div class="card-body"><h2 class="h4">{{ $atual ? 'Compromisso confirmado' : ($visitaRealizada ? 'Confirmar visita complementar' : 'Confirmar visita') }}</h2>
            @if ($visitaRealizada)<p>A avaliação está em aguardo. A nova visita complementa a mesma avaliação: o relatório dela será analisado junto com os anteriores.</p>@endif
            @if ($atual)<p>{{ $atual->inicio_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} até {{ $atual->fim_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} — {{ $atual->local_atendimento }}</p>@endif
            @php($formularioAgenda = $atual ? 'reagendamento' : 'confirmacao')
            <form method="POST" action="{{ $atual ? route('interno.agenda.reagendamentos.store', $atual) : route('interno.agenda.store', $pedido) }}">@csrf
                @include('agenda.formulario', ['formulario' => $formularioAgenda, 'versao' => $atual?->versao ?? $pedido->versao])
                <button class="btn btn-primary">{{ $atual ? 'Reagendar visita' : 'Confirmar visita' }}</button>
            </form>
        </div></section>
        @endif
        @if ($atual)
            @foreach (['cancelamento' => 'Cancelar visita', 'nao_realizacao' => 'Registrar não realização'] as $formulario => $rotulo)
                @if ($formulario === 'cancelamento' || $atual->inicio_previsto_em->lte(now()))
                    <section class="card mb-4"><div class="card-body"><h2 class="h5">{{ $rotulo }}</h2><p>Libera o horário e preserva a admissão e o histórico.</p><form method="POST" action="{{ route('interno.agenda.'.($formulario === 'cancelamento' ? 'cancelamento' : 'nao-realizacao').'.store', $atual) }}">@csrf
                        @include('agenda.formulario', ['formulario' => $formulario, 'versao' => $atual->versao])<button class="btn btn-outline-danger">{{ $rotulo }}</button>
                    </form></div></section>
                @endif
            @endforeach
        @endif
        <h2 class="h4">Contatos de acompanhamento</h2>
        @forelse ($contatos as $contato)<article class="border rounded p-3 mb-3"><h3 class="h6">#{{ $contato->id }} — {{ $contato->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $contato->canal }} · {{ $contato->resultado }}</h3><p>{{ $contato->finalidade }} — {{ $contato->destinatario }}<br>Registrado por {{ $contato->registradoPor->name }}</p><p class="text-break" style="white-space: pre-wrap">{{ $contato->resumo }}</p>@if ($contato->proxima_acao)<p>Próxima ação: {{ $contato->proxima_acao }}</p>@endif</article>@empty<p>Nenhum contato de acompanhamento registrado.</p>@endforelse
        {{ $contatos->links() }}
        <h2 class="h4 mt-4">Histórico da agenda</h2>
        @forelse ($agendamentos as $agenda)<article class="border rounded p-3 mb-3"><h3 class="h6">Compromisso #{{ $agenda->id }} — {{ ['confirmado' => 'Confirmado', 'substituido' => 'Reagendado', 'cancelado' => 'Cancelado', 'nao_realizado' => 'Não realizado', 'realizado' => 'Realizado'][$agenda->status] }}</h3><p>{{ $agenda->inicio_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} até {{ $agenda->fim_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $agenda->responsavel->name }}<br>{{ $agenda->modalidade === 'domicilio' ? 'Domicílio' : 'Banco de Leite' }}: {{ $agenda->local_atendimento }}</p>@foreach ($agenda->eventos as $evento)<p>{{ $evento->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} · {{ $evento->autor->name }} · {{ $evento->acao }}: {{ $evento->motivo }} @if ($evento->agendamento_resultante_id) → compromisso #{{ $evento->agendamento_resultante_id }} @endif</p>@endforeach</article>@empty<p>Nenhum agendamento registrado.</p>@endforelse
        {{ $agendamentos->links() }}
        <p>Relatório da visita, documentos, exames e avaliação para doação continuam pendentes das próximas etapas.</p>
    </div>
</x-app-layout>
