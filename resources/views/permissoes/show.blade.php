@php
    $descricoes = [
        'atender-solicitacoes' => 'Fila de solicitações, análise, contatos, conferência e admissão administrativa.',
        'gerenciar-agenda' => 'Contatos de acompanhamento, confirmação, reagendamento e cancelamento de visitas.',
        'criar-funcionarios' => 'Cadastrar contas de funcionário. Não permite conceder permissões.',
        'consultar-relatorios-visita' => 'Ler as versões publicadas dos relatórios de visita, sem rascunhos.',
        'registrar-relatorios-visita' => 'Registrar visitas e escrever, finalizar e corrigir relatórios. Inclui a consulta.',
        'consultar-avaliacoes' => 'Ler a situação e o histórico de decisões das avaliações, sem decidir.',
        'avaliar-doadoras' => 'Analisar o relatório publicado e decidir: liberar para exames, deixar em aguardo ou considerar não apta. Inclui a consulta de avaliações e de relatórios publicados.',
    ];
@endphp
<x-app-layout>
    <div class="container py-4 blh-form-width">
        <a href="{{ route('interno.permissoes.index') }}">Voltar às permissões</a>
        <h1 class="h3 mt-3">Permissões de {{ $funcionario->name }}</h1>
        <p>{{ $funcionario->email }}</p>
        <p>Informe o motivo de cada alteração. A mudança vale a partir da próxima ação do funcionário, inclusive em sessão já aberta.</p>
        @if (session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
        @php
            $errosConta = $errors->getBag('conta');
        @endphp
        <section class="card mb-4 {{ $funcionario->estaAtivo() ? '' : 'border-danger' }}" aria-labelledby="situacao-conta"><div class="card-body">
            <h2 class="h5" id="situacao-conta">Situação da conta</h2>
            @if ($funcionario->estaAtivo())
                <p class="mb-2"><span class="badge text-bg-success">Ativa</span></p>
                @if ($visitasFuturas->isNotEmpty())
                    <div class="alert alert-warning" role="status"><p>Responsável por visitas futuras. Reagende-as para outra pessoa ou cancele-as na agenda antes de desativar a conta:</p>
                        <ul class="mb-0">@foreach ($visitasFuturas as $visita)<li>{{ $visita->inicio_previsto_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }} — {{ $visita->solicitacao->interessada?->nome_completo }}@can('gerenciar-agenda') (<a href="{{ route('interno.agenda.acompanhamentos.show', $visita->solicitacao_id) }}">abrir na agenda</a>)@endcan</li>@endforeach</ul>
                    </div>
                @endif
            @else
                <p class="mb-2"><span class="badge text-bg-danger">Desativada</span> desde {{ $funcionario->desativado_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}, por {{ $funcionario->desativadoPor?->name }}. O acesso está bloqueado e as permissões abaixo estão suspensas.</p>
            @endif
            @if ($errosConta->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errosConta->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
            <form method="POST" action="{{ route($funcionario->estaAtivo() ? 'interno.permissoes.desativacao.store' : 'interno.permissoes.reativacao.store', $funcionario) }}">@csrf
                <label class="form-label" for="motivo-conta">Motivo da {{ $funcionario->estaAtivo() ? 'desativação' : 'reativação' }}</label>
                <textarea class="form-control mb-2 @if ($errosConta->any()) is-invalid @endif" id="motivo-conta" name="motivo" rows="2" maxlength="2000" required>{{ $errosConta->any() ? old('motivo') : '' }}</textarea>
                <button class="btn {{ $funcionario->estaAtivo() ? 'btn-danger' : 'btn-success' }}" @disabled($funcionario->estaAtivo() && $visitasFuturas->isNotEmpty())>{{ $funcionario->estaAtivo() ? 'Desativar conta' : 'Reativar conta' }}</button>
            </form>
        </div></section>
        @foreach ($catalogo as $permissao)
            @php
                $concessao = $concessoes->get($permissao->id);
                $comErro = $errors->any() && (string) old('permissao_id') === (string) $permissao->id;
            @endphp
            <section class="card mb-3" aria-labelledby="permissao-{{ $permissao->id }}"><div class="card-body">
                <h2 class="h5" id="permissao-{{ $permissao->id }}">{{ $permissao->nome }}</h2>
                <p class="mb-2">{{ $descricoes[$permissao->codigo] ?? '' }}</p>
                @if ($concessao)
                    <p class="mb-2">@if ($funcionario->estaAtivo())<span class="badge text-bg-success">Ativa</span>@else<span class="badge text-bg-warning">Suspensa</span>@endif · concedida em {{ $concessao->concedida_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}, por {{ $concessao->concedidaPor->name }}.</p>
                @else
                    <p class="mb-2"><span class="badge text-bg-light border">Não concedida</span></p>
                @endif
                @if ($comErro)<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach ($errors->all() as $erro)<li>{{ $erro }}</li>@endforeach</ul></div>@endif
                <form method="POST" action="{{ route($concessao ? 'interno.permissoes.revogacoes.store' : 'interno.permissoes.concessoes.store', $funcionario) }}">@csrf
                    <input type="hidden" name="permissao_id" value="{{ $permissao->id }}">
                    <label class="form-label" for="motivo-{{ $permissao->id }}">Motivo da {{ $concessao ? 'revogação' : 'concessão' }}</label>
                    <textarea class="form-control mb-2 @if ($comErro) is-invalid @endif" id="motivo-{{ $permissao->id }}" name="motivo" rows="2" maxlength="2000" required>{{ $comErro ? old('motivo') : '' }}</textarea>
                    <button class="btn {{ $concessao ? 'btn-outline-danger' : 'btn-primary' }}">{{ $concessao ? 'Revogar' : 'Conceder' }}<span class="visually-hidden"> {{ $permissao->nome }}</span></button>
                </form>
            </div></section>
        @endforeach
        <h2 class="h4 mt-4">Histórico de alterações</h2>
        <ul class="list-group mb-3">
            @forelse ($eventos as $evento)
                <li class="list-group-item">
                    <strong>{{ $evento->acao === 'concessao' ? 'Concedida' : 'Revogada' }}:</strong> {{ $evento->permissao->nome }} · {{ $evento->autor->name }} · {{ $evento->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                    <div class="small text-break">Motivo: {{ $evento->motivo }}</div>
                </li>
            @empty
                <li class="list-group-item">Nenhuma alteração registrada.</li>
            @endforelse
        </ul>
        {{ $eventos->links() }}
        <h2 class="h4 mt-4">Histórico da conta</h2>
        <ul class="list-group mb-3">
            @forelse ($eventosConta as $evento)
                <li class="list-group-item">
                    <strong>{{ $evento->acao === 'desativacao' ? 'Desativada' : 'Reativada' }}</strong> · {{ $evento->autor->name }} · {{ $evento->ocorrido_em->setTimezone('America/Sao_Paulo')->format('d/m/Y H:i') }}
                    <div class="small text-break">Motivo: {{ $evento->motivo }}</div>
                </li>
            @empty
                <li class="list-group-item">Conta nunca desativada.</li>
            @endforelse
        </ul>
        {{ $eventosConta->links() }}
    </div>
</x-app-layout>
