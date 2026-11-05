<x-app-layout>
    <div class="container py-4">
        <h1 class="h3">Agenda de visitas</h1>
        <p>Horários de Marília (America/Sao_Paulo). Considere o deslocamento ao combinar as visitas.</p>
        @if ($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
        <form method="GET" action="{{ route('interno.agenda.index') }}" class="row g-3 mb-4">
            @foreach (['inicio' => 'De', 'fim' => 'Até'] as $campo => $rotulo)
                <div class="col-sm-6 col-lg-2"><label class="form-label" for="{{ $campo }}">{{ $rotulo }}</label><input class="form-control" type="date" id="{{ $campo }}" name="{{ $campo }}" value="{{ $filtros[$campo] ?? '' }}"></div>
            @endforeach
            <div class="col-sm-6 col-lg-2"><label class="form-label" for="modalidade">Local</label><select class="form-select" id="modalidade" name="modalidade"><option value="">Todos</option>@foreach (['domicilio' => 'Domicílio', 'banco' => 'Banco de Leite'] as $valor => $texto)<option value="{{ $valor }}" @selected(($filtros['modalidade'] ?? '') === $valor)>{{ $texto }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-lg-2"><label class="form-label" for="situacao">Situação</label><select class="form-select" id="situacao" name="situacao"><option value="">Todas</option>@foreach (['confirmado' => 'Confirmado', 'substituido' => 'Reagendado', 'cancelado' => 'Cancelado', 'nao_realizado' => 'Não realizado', 'realizado' => 'Realizado'] as $valor => $texto)<option value="{{ $valor }}" @selected(($filtros['situacao'] ?? '') === $valor)>{{ $texto }}</option>@endforeach</select></div>
            <div class="col-sm-6 col-lg-4"><label class="form-label" for="responsavel">Responsável</label><select class="form-select" id="responsavel" name="responsavel"><option value="">Todos</option>@foreach ($responsaveis as $responsavel)<option value="{{ $responsavel->id }}" @selected((string) ($filtros['responsavel'] ?? '') === (string) $responsavel->id)>{{ $responsavel->name }}</option>@endforeach</select></div>
            <div class="col-12"><button class="btn btn-primary">Filtrar compromissos</button> <a href="{{ route('interno.agenda.index') }}" class="btn btn-outline-secondary">Limpar</a></div>
        </form>
        <div class="table-responsive"><table class="table align-middle"><caption>Compromissos registrados</caption><thead><tr><th>Interessada</th><th>Início / fim</th><th>Responsável</th><th>Situação</th><th>Ação</th></tr></thead><tbody>
        @forelse ($agendamentos as $agenda)
            <tr><td>{{ $agenda->solicitacao->interessada->nome_completo }}</td><td>{{ $agenda->inicio_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}<br>{{ $agenda->fim_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</td><td>{{ $agenda->responsavel->name }}</td><td>{{ ['confirmado' => 'Confirmado', 'substituido' => 'Reagendado', 'cancelado' => 'Cancelado', 'nao_realizado' => 'Não realizado', 'realizado' => 'Realizado'][$agenda->status] }}</td><td><a href="{{ route('interno.agenda.acompanhamentos.show', $agenda->solicitacao_id) }}">Abrir acompanhamento #{{ $agenda->solicitacao_id }}</a></td></tr>
        @empty<tr><td colspan="5">Nenhum compromisso encontrado.</td></tr>@endforelse
        </tbody></table></div>
        {{ $agendamentos->links() }}
        <h2 class="h4 mt-4">Aguardando agendamento</h2>
        <p>Cadastros admitidos sem reserva atual e sem visita realizada, incluindo visitas canceladas ou não realizadas. Os filtros acima se aplicam somente aos compromissos.</p>
        <ul class="list-group mb-3">@forelse ($pendentes as $pedido)<li class="list-group-item"><a href="{{ route('interno.agenda.acompanhamentos.show', $pedido) }}">{{ $pedido->interessada->nome_completo }} — acompanhamento #{{ $pedido->id }}</a>@if ($pedido->visita_realizada) · visita complementar (avaliação em aguardo)@endif</li>@empty<li class="list-group-item">Nenhum acompanhamento aguardando agendamento.</li>@endforelse</ul>
        {{ $pendentes->links() }}
    </div>
</x-app-layout>
