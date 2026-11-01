<x-app-layout>
    <div class="container py-4">
        <h1 class="h3">Exames e documentos</h1>
        <p>Avaliações liberadas para exames. A doadora só avança quando há ao menos um item solicitado, todos os solicitados estão aceitos e nenhum está vencido.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Interessada</th><th>Visita</th><th>Itens</th><th>Exigências</th><th>Exames</th></tr></thead><tbody>
            @forelse ($avaliacoes as $item)
                @php
                    $pedidos = $item->itens->where('estado', '!=', 'nao_solicitado');
                    $vencidos = $item->itens->filter->vencido()->count();
                @endphp
                <tr><td>{{ $item->atendimento->agendamento->solicitacao->interessada->nome_completo }}</td>
                    <td>{{ $item->atendimento->inicio_efetivo_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}<br>{{ $item->atendimento->responsavel->name }}</td>
                    <td>{{ $pedidos->count() }} solicitado(s) · {{ $pedidos->where('estado', 'aceito')->count() }} aceito(s)@if ($vencidos)<br><span class="badge text-bg-danger">{{ $vencidos }} vencido(s)</span>@endif</td>
                    <td>{{ App\Models\Avaliacao::exigenciasCumpridas($item->itens) ? 'Cumpridas' : 'Pendentes' }}</td>
                    <td><a href="{{ route('interno.exames.show', $item) }}">Abrir exames da avaliação #{{ $item->id }}</a></td></tr>
            @empty<tr><td colspan="5">Nenhuma avaliação liberada para exames.</td></tr>@endforelse
        </tbody></table></div>
        {{ $avaliacoes->links() }}
    </div>
</x-app-layout>
