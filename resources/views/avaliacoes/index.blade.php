<x-app-layout>
    <div class="container py-4">
        <h1 class="h3">Avaliações</h1>
        <p>Cada visita com relatório publicado gera uma avaliação. A análise do relatório não habilita ninguém para doar.</p>
        @if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $erro)<p class="mb-0">{{ $erro }}</p>@endforeach</div>@endif
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4"><label for="estado" class="form-label">Situação da avaliação</label><select id="estado" name="estado" class="form-select"><option value="">Todas</option>
                @foreach (App\Models\Avaliacao::ESTADOS as $valor => $rotulo)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $rotulo }}</option>@endforeach
            </select></div>
            <div class="col-md-4 align-self-end"><button class="btn btn-primary">Filtrar</button> <a class="btn btn-outline-secondary" href="{{ route('interno.avaliacoes.index') }}">Limpar</a></div>
        </form>
        <div class="table-responsive"><table class="table"><thead><tr><th>Interessada</th><th>Visita</th><th>Situação</th><th>Avaliação</th></tr></thead><tbody>
            @forelse ($avaliacoes as $item)
                <tr><td>{{ $item->atendimento->agendamento->solicitacao->interessada->nome_completo }}</td><td>{{ $item->atendimento->inicio_efetivo_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}<br>{{ $item->atendimento->responsavel->name }}</td><td>{{ App\Models\Avaliacao::ESTADOS[$item->estado] }}</td><td><a href="{{ route('interno.avaliacoes.show', $item) }}">Abrir avaliação #{{ $item->id }}</a></td></tr>
            @empty<tr><td colspan="4">Nenhuma avaliação encontrada.</td></tr>@endforelse
        </tbody></table></div>
        {{ $avaliacoes->links() }}
    </div>
</x-app-layout>
