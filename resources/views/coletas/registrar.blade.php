<x-public-layout titulo="Registrar coleta | Banco de Leite Humano">
    <section class="blh-hero blh-coleta__hero" aria-labelledby="titulo-coleta">
        <div class="blh-wrap blh-coleta__hero-inner">
            <div>
                <p class="blh-coleta__sobretitulo">Coletas</p>
                <h1 id="titulo-coleta">Registrar coleta e recebimento</h1>
                <p class="blh-coleta__intro">Registre as informações do leite recebido pelo Banco de Leite.</p>
            </div>
            <div class="blh-coleta__selo" aria-label="Registro de coleta">Registro</div>
        </div>
    </section>

    <div class="blh-wrap blh-coleta__layout">
        <section class="blh-coleta__cartao" aria-labelledby="titulo-dados-recebimento">
            <header class="blh-coleta__cabecalho-cartao">
                <div>
                    <p class="blh-coleta__etapa">Entrada de leite</p>
                    <h2 id="titulo-dados-recebimento">Dados do recebimento</h2>
                </div>
                <span class="blh-coleta__passo">Etapa 1</span>
            </header>

            <div class="blh-coleta__corpo">
                <div class="blh-coleta__grade">
                    <div class="blh-coleta__campo">
                        <span class="form-label">Doadora</span>
                        <div class="form-control blh-coleta__campo-demo" aria-label="Doadora: selecionar doadora">
                            <span>Selecionar doadora</span>
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>
                        </div>
                    </div>

                    <div class="blh-coleta__campo">
                        <span class="form-label">Data do recebimento</span>
                        <div class="form-control blh-coleta__campo-demo" aria-label="Data do recebimento: dd/mm/aaaa">
                            <span>dd/mm/aaaa</span>
                            <svg viewBox="0 0 16 16" aria-hidden="true"><rect x="2.5" y="4" width="11" height="9.5" rx="1.5"/><path d="M5 2.5v3M11 2.5v3M2.5 7h11"/></svg>
                        </div>
                    </div>

                    <div class="blh-coleta__campo">
                        <span class="form-label">Quantidade recebida</span>
                        <div class="form-control blh-coleta__campo-demo" aria-label="Quantidade recebida: informar quantidade">Informar quantidade</div>
                    </div>

                    <div class="blh-coleta__campo">
                        <span class="form-label">Unidade</span>
                        <div class="form-control blh-coleta__campo-demo" aria-label="Unidade: selecionar unidade">
                            <span>Selecionar unidade</span>
                            <svg viewBox="0 0 16 16" aria-hidden="true"><path d="m3 6 5 5 5-5"/></svg>
                        </div>
                    </div>

                    <div class="blh-coleta__campo blh-coleta__campo--largo">
                        <span class="form-label">Observações</span>
                        <div class="form-control blh-coleta__campo-demo blh-coleta__campo-demo--texto" aria-label="Observações: informações complementares">Informações complementares</div>
                    </div>
                </div>

                <div class="blh-coleta__aviso" role="status">
                    <span class="blh-coleta__aviso-icone" aria-hidden="true">i</span>
                    <p><strong>Registro indisponível no momento.</strong> Nenhum dado será salvo nesta tela.</p>
                </div>

                <footer class="blh-coleta__acoes">
                    <a class="btn btn-outline-secondary" href="{{ url('/') }}">Voltar ao início</a>
                    <button class="btn btn-blh" type="button" disabled>Registrar recebimento</button>
                </footer>
            </div>
        </section>
    </div>
</x-public-layout>
