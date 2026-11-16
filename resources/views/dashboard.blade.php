<x-app-layout>
    <div class="blh-painel">
        <section class="blh-painel__abertura" aria-labelledby="titulo-painel">
            <div class="blh-wrap">
                <p class="blh-painel__rotulo">Painel</p>
                <h1 id="titulo-painel">Olá, {{ Illuminate\Support\Str::before(Auth::user()->name, ' ') }}</h1>
                <p class="blh-painel__texto">Você está conectado. Escolha abaixo a área em que quer trabalhar.</p>
            </div>
        </section>

        <div class="blh-wrap">
            @canany(['atender-solicitacoes', 'gerenciar-agenda', 'consultar-relatorios-visita', 'consultar-avaliacoes', 'consultar-exames', 'criar-funcionarios', 'gerenciar-permissoes', 'gerenciar-retencao'])
                @canany(['atender-solicitacoes', 'gerenciar-agenda', 'consultar-relatorios-visita'])
                    <section class="blh-painel__secao" aria-labelledby="titulo-atendimento">
                        <h2 id="titulo-atendimento">Atendimento</h2>
                        <ul class="blh-cartoes">
                            @can('atender-solicitacoes')
                                <li><a class="blh-cartao" href="{{ route('interno.solicitacoes.index') }}">
                                    <h3>Solicitações</h3>
                                    <p>Fila de pedidos de doação: análise, contatos e admissão.</p>
                                </a></li>
                            @endcan
                            @can('gerenciar-agenda')
                                <li><a class="blh-cartao" href="{{ route('interno.agenda.index') }}">
                                    <h3>Agenda</h3>
                                    <p>Visitas marcadas, contatos de acompanhamento e reagendamentos.</p>
                                </a></li>
                            @endcan
                            @can('consultar-relatorios-visita')
                                <li><a class="blh-cartao" href="{{ route('interno.relatorios.index') }}">
                                    <h3>Relatórios de visita</h3>
                                    <p>Registro e consulta do relato de cada visita realizada.</p>
                                </a></li>
                            @endcan
                        </ul>
                    </section>
                @endcanany

                @canany(['consultar-avaliacoes', 'consultar-exames'])
                    <section class="blh-painel__secao" aria-labelledby="titulo-habilitacao">
                        <h2 id="titulo-habilitacao">Análise e habilitação</h2>
                        <ul class="blh-cartoes">
                            @can('consultar-avaliacoes')
                                <li><a class="blh-cartao" href="{{ route('interno.avaliacoes.index') }}">
                                    <h3>Avaliações</h3>
                                    <p>Análise do relato da visita e decisão sobre cada interessada.</p>
                                </a></li>
                                <li><a class="blh-cartao" href="{{ route('interno.doadoras.index') }}">
                                    <h3>Doadoras</h3>
                                    <p>Doadoras habilitadas, situação atual e histórico.</p>
                                </a></li>
                            @endcan
                            @can('consultar-exames')
                                <li><a class="blh-cartao" href="{{ route('interno.exames.index') }}">
                                    <h3>Exames</h3>
                                    <p>Exames e documentos exigidos em cada avaliação.</p>
                                </a></li>
                            @endcan
                        </ul>
                    </section>
                @endcanany

                <section class="blh-painel__secao" aria-labelledby="titulo-coleta">
                    <h2 id="titulo-coleta">Coleta</h2>
                    <ul class="blh-cartoes">
                        <li><a class="blh-cartao" href="{{ route('interno.coletas.registrar') }}">
                            <h3>Coletas</h3>
                            <p>Registro do leite recebido pelo Banco de Leite (prévia, ainda sem gravação).</p>
                        </a></li>
                    </ul>
                </section>

                @canany(['criar-funcionarios', 'gerenciar-permissoes', 'gerenciar-retencao'])
                    <section class="blh-painel__secao" aria-labelledby="titulo-administracao">
                        <h2 id="titulo-administracao">Administração</h2>
                        <ul class="blh-cartoes">
                            @can('criar-funcionarios')
                                <li><a class="blh-cartao" href="{{ route('interno.funcionarios.create') }}">
                                    <h3>Cadastrar funcionário</h3>
                                    <p>Criar o acesso de uma nova pessoa da equipe.</p>
                                </a></li>
                            @endcan
                            @can('gerenciar-permissoes')
                                <li><a class="blh-cartao" href="{{ route('interno.permissoes.index') }}">
                                    <h3>Permissões</h3>
                                    <p>Liberar ou retirar acessos e desativar contas da equipe.</p>
                                </a></li>
                            @endcan
                            @can('gerenciar-retencao')
                                <li><a class="blh-cartao" href="{{ route('interno.retencao.index') }}">
                                    <h3>Retenção</h3>
                                    <p>Descartar arquivos de exames e valores de correções com prazo vencido.</p>
                                </a></li>
                            @endcan
                        </ul>
                    </section>
                @endcanany
            @else
                <section class="blh-painel__secao">
                    <div class="blh-painel__aviso" role="status">
                        <h2>Seu acesso ainda não foi liberado</h2>
                        <p>Sua conta está ativa, mas nenhuma área foi liberada para você. Peça a uma pessoa administradora para configurar o seu acesso.</p>
                    </div>
                </section>
            @endcanany
        </div>
    </div>
</x-app-layout>
