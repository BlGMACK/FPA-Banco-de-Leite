<x-app-layout>
    <div class="container py-4">
        <h1 class="h3">Relatórios de visita</h1>
        <p>A visita e o relatório não representam aprovação para doar.</p>
        @if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $erro)<p class="mb-0">{{ $erro }}</p>@endforeach</div>@endif
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4"><label for="estado" class="form-label">Situação do relatório</label><select id="estado" name="estado" class="form-select"><option value="">Todas</option>
                @foreach (['rascunho' => 'Rascunho', 'finalizado' => 'Finalizado', 'em_correcao' => 'Em correção'] as $valor => $rotulo)
                    @if ($escrita || $valor !== 'rascunho')<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $rotulo }}</option>@endif
                @endforeach
            </select></div>
            <div class="col-md-5"><label for="responsavel" class="form-label">Responsável pela visita</label><select id="responsavel" name="responsavel" class="form-select"><option value="">Todos</option>@foreach ($responsaveis as $pessoa)<option value="{{ $pessoa->id }}" @selected((string) ($filtros['responsavel'] ?? '') === (string) $pessoa->id)>{{ $pessoa->name }}</option>@endforeach</select></div>
            <div class="col-md-3 align-self-end"><button class="btn btn-primary">Filtrar</button> <a class="btn btn-outline-secondary" href="{{ route('interno.relatorios.index') }}">Limpar</a></div>
        </form>
        <div class="table-responsive"><table class="table"><thead><tr><th>Interessada</th><th>Visita</th><th>Situação</th><th>Relatório</th></tr></thead><tbody>
            @forelse ($relatorios as $item)
                <tr><td>{{ $item->atendimento->agendamento->solicitacao->interessada->nome_completo }}</td><td>{{ $item->atendimento->inicio_efetivo_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}<br>{{ $item->atendimento->responsavel->name }}</td><td>{{ ['rascunho' => 'Rascunho', 'finalizado' => 'Finalizado', 'em_correcao' => 'Em correção'][$item->estado] }}</td><td><a href="{{ route('interno.relatorios.show', $item) }}">Abrir relatório #{{ $item->id }}</a></td></tr>
            @empty<tr><td colspan="4">Nenhum relatório disponível para consulta.</td></tr>@endforelse
        </tbody></table></div>
        {{ $relatorios->links() }}
        @if ($pendentes !== null)
            <h2 class="h4 mt-4">Visitas a registrar</h2><p>Compromissos confirmados. Registre a realização somente depois de a visita ocorrer. Os filtros acima se aplicam aos relatórios.</p>
            <ul class="list-group mb-3">@forelse ($pendentes as $agenda)<li class="list-group-item"><a href="{{ route('interno.relatorios.visitas.create', $agenda) }}">{{ $agenda->solicitacao->interessada->nome_completo }} — {{ $agenda->inicio_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}</a> · {{ $agenda->responsavel->name }}</li>@empty<li class="list-group-item">Nenhuma visita aguardando registro.</li>@endforelse</ul>
            {{ $pendentes->links() }}
        @endif
    </div>
</x-app-layout>
