<x-app-layout>
    <div class="container py-4">
        <h1 class="h3">Permissões dos funcionários</h1>
        <p>Cada funcionário recebe somente as permissões concedidas aqui; elas podem ser acumuladas. Administradores têm acesso a todos os módulos e não aparecem nesta lista. Toda concessão e revogação fica registrada com autor, data e motivo.</p>
        @can('criar-funcionarios')<p><a href="{{ route('interno.funcionarios.create') }}">Cadastrar funcionário</a></p>@endcan
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead><tr><th scope="col">Funcionário</th><th scope="col">Permissões ativas</th><th scope="col"><span class="visually-hidden">Ações</span></th></tr></thead>
                <tbody>
                    @forelse ($funcionarios as $funcionario)
                        <tr>
                            <td>{{ $funcionario->name }}<br><span class="small text-body-secondary">{{ $funcionario->email }}</span></td>
                            <td>@forelse ($funcionario->concessoes as $concessao)<span class="badge text-bg-secondary me-1 mb-1">{{ $concessao->permissao->nome }}</span>@empty<span class="text-body-secondary">Nenhuma</span>@endforelse</td>
                            <td class="text-end"><a href="{{ route('interno.permissoes.show', $funcionario) }}">Gerenciar<span class="visually-hidden"> permissões de {{ $funcionario->name }}</span></a></td>
                        </tr>
                    @empty
                        <tr><td colspan="3">Nenhum funcionário cadastrado.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $funcionarios->links() }}
    </div>
</x-app-layout>
