<nav id="navegacao" class="collapse blh-lateral" aria-label="Navegação principal">
    <div class="blh-lateral__interno">
        <p class="blh-lateral__rotulo">Menu</p>
        <ul>
            <li><a href="{{ route('dashboard') }}" @if (request()->routeIs('dashboard')) aria-current="page" @endif>Painel</a></li>
            @can('atender-solicitacoes')
                <li><a href="{{ route('interno.solicitacoes.index') }}" @if (request()->routeIs('interno.solicitacoes.*')) aria-current="page" @endif>Solicitações</a></li>
            @endcan
            @can('gerenciar-agenda')
                <li><a href="{{ route('interno.agenda.index') }}" @if (request()->routeIs('interno.agenda.*')) aria-current="page" @endif>Agenda</a></li>
            @endcan
            @can('consultar-relatorios-visita')
                <li><a href="{{ route('interno.relatorios.index') }}" @if (request()->routeIs('interno.relatorios.*')) aria-current="page" @endif>Relatórios de visita</a></li>
            @endcan
            @can('consultar-avaliacoes')
                <li><a href="{{ route('interno.avaliacoes.index') }}" @if (request()->routeIs('interno.avaliacoes.*')) aria-current="page" @endif>Avaliações</a></li>
                <li><a href="{{ route('interno.doadoras.index') }}" @if (request()->routeIs('interno.doadoras.*')) aria-current="page" @endif>Doadoras</a></li>
            @endcan
            @can('consultar-exames')
                <li><a href="{{ route('interno.exames.index') }}" @if (request()->routeIs('interno.exames.*')) aria-current="page" @endif>Exames</a></li>
            @endcan
            @can('criar-funcionarios')
                <li><a href="{{ route('interno.funcionarios.create') }}" @if (request()->routeIs('interno.funcionarios.*')) aria-current="page" @endif>Cadastrar funcionário</a></li>
            @endcan
            @can('gerenciar-permissoes')
                <li><a href="{{ route('interno.permissoes.index') }}" @if (request()->routeIs('interno.permissoes.*')) aria-current="page" @endif>Permissões</a></li>
            @endcan
            @can('gerenciar-retencao')
                <li><a href="{{ route('interno.retencao.index') }}" @if (request()->routeIs('interno.retencao.*')) aria-current="page" @endif>Retenção</a></li>
            @endcan
        </ul>
    </div>
</nav>
