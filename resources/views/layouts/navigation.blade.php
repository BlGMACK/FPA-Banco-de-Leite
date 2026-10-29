<nav class="navbar navbar-expand-lg bg-white border-bottom" aria-label="Navegação principal">
    <div class="container">
        <a class="navbar-brand fw-semibold" href="{{ route('dashboard') }}">Banco de Leite</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navegacao" aria-controls="navegacao" aria-expanded="false" aria-label="Abrir menu"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="navegacao">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">Painel</a></li>
                @can('atender-solicitacoes')
                    <li class="nav-item"><a class="nav-link {{ request()->routeIs('interno.solicitacoes.*') ? 'active' : '' }}" href="{{ route('interno.solicitacoes.index') }}">Solicitações</a></li>
                @endcan
                @can('gerenciar-agenda')
                    <li class="nav-item"><a class="nav-link" href="{{ route('interno.agenda.index') }}">Agenda</a></li>
                @endcan
                @can('consultar-relatorios-visita')
                    <li class="nav-item"><a class="nav-link" href="{{ route('interno.relatorios.index') }}">Relatórios de visita</a></li>
                @endcan
                @can('criar-funcionarios')
                    <li class="nav-item"><a class="nav-link" href="{{ route('interno.funcionarios.create') }}">Cadastrar funcionário</a></li>
                @endcan
            </ul>
            <div class="d-flex flex-wrap align-items-center gap-3 py-2">
                <span class="small text-body-secondary">{{ Auth::user()->name }}</span>
                <a href="{{ route('profile.edit') }}" class="btn btn-outline-secondary btn-sm">Perfil</a>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="btn btn-outline-danger btn-sm" type="submit">Sair</button></form>
            </div>
        </div>
    </div>
</nav>
