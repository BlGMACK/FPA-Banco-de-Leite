<x-app-layout>
    <div class="container py-4">
        <h1 class="h3">Doadoras</h1>
        <p>Habilitações registradas pela decisão final da avaliação. Os dados pessoais são os da interessada, sem cópia.</p>
        @if ($errors->any())<div class="alert alert-danger" role="alert">@foreach ($errors->all() as $erro)<p class="mb-0">{{ $erro }}</p>@endforeach</div>@endif
        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4"><label for="estado" class="form-label">Situação da habilitação</label><select id="estado" name="estado" class="form-select"><option value="">Todas</option>
                @foreach (App\Models\Doadora::ESTADOS as $valor => $rotulo)<option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $rotulo }}</option>@endforeach
            </select></div>
            <div class="col-md-4 align-self-end"><button class="btn btn-primary">Filtrar</button> <a class="btn btn-outline-secondary" href="{{ route('interno.doadoras.index') }}">Limpar</a></div>
        </form>
        <div class="table-responsive"><table class="table"><thead><tr><th>Doadora</th><th>Tipo</th><th>Situação</th><th>Habilitação</th></tr></thead><tbody>
            @forelse ($doadoras as $item)
                <tr><td>{{ $item->interessada->nome_completo }}</td>
                    <td>{{ App\Models\Doadora::TIPOS[$item->tipo] }}@if ($item->valida_ate)<br><small>até {{ $item->valida_ate->format('d/m/Y') }}</small>@endif</td>
                    <td>{{ App\Models\Doadora::ESTADOS[$item->estado] }}@if ($item->vencida()) <span class="badge text-bg-danger">Prazo vencido</span>@endif</td>
                    <td><a href="{{ route('interno.doadoras.show', $item) }}">Abrir habilitação #{{ $item->id }}</a></td></tr>
            @empty<tr><td colspan="4">Nenhuma doadora encontrada.</td></tr>@endforelse
        </tbody></table></div>
        {{ $doadoras->links() }}
    </div>
</x-app-layout>
