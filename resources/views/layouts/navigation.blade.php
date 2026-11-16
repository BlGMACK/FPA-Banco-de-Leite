@php
    $usuario = Auth::user();
    $iniciais = collect(preg_split('/\s+/', trim($usuario->name)))->filter()->map(fn (string $parte): string => mb_substr($parte, 0, 1))->pipe(
        fn ($partes) => mb_strtoupper($partes->first().($partes->count() > 1 ? $partes->last() : ''))
    );
@endphp
<aside id="navegacao" class="collapse blh-lateral">
    <div class="blh-lateral__interno">
        <section class="blh-conta" aria-label="Sua conta">
            <button class="blh-conta__botao" type="button" data-bs-toggle="collapse" data-bs-target="#conta-dados" aria-controls="conta-dados" aria-expanded="false">
                <span class="blh-conta__avatar" aria-hidden="true">{{ $iniciais }}</span>
                <span class="blh-conta__nome">{{ $usuario->name }}</span>
                <svg class="blh-conta__seta" viewBox="0 0 16 16" aria-hidden="true" focusable="false"><path d="M4 6l4 4 4-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div id="conta-dados" class="collapse">
                <dl class="blh-conta__dados">
                    <dt>E-mail</dt>
                    <dd>{{ $usuario->email }}</dd>
                    <dt>Acesso</dt>
                    <dd>{{ $usuario->isAdmin() ? 'Administração' : 'Equipe' }}</dd>
                </dl>
                <div class="blh-conta__acoes">
                    <a class="blh-conta__acao" href="{{ route('profile.edit') }}" @if (request()->routeIs('profile.*')) aria-current="page" @endif>Meu perfil</a>
                    <form method="POST" action="{{ route('logout') }}">@csrf<button class="blh-conta__acao blh-conta__acao--sair" type="submit">Sair</button></form>
                </div>
            </div>
        </section>

        <nav aria-label="Navegação principal">
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
                <li><a href="{{ route('interno.coletas.registrar') }}" @if (request()->routeIs('interno.coletas.*')) aria-current="page" @endif>Coletas</a></li>
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
        </nav>
    </div>
</aside>
