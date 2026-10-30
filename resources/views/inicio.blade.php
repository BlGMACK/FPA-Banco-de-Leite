<x-public-layout>
    <section class="blh-hero" aria-labelledby="titulo-inicio">
        <div class="blh-wrap blh-hero__grade">
            <div>
                <h1 id="titulo-inicio">Doe leite humano. Alimente um bebê que precisa.</h1>
                <p class="blh-hero__texto">O Banco de Leite Humano de Marília recebe doações de mães que têm leite de sobra e querem ajudar. Envie seus dados sem criar uma conta: a equipe analisa sua solicitação e entra em contato.</p>
                <a class="btn btn-blh btn-lg" href="{{ route('solicitacoes.create') }}">Quero doar leite humano</a>
                <p class="blh-hero__nota">Leva poucos minutos. Enviar o formulário não é aprovação para doar.</p>
            </div>
            <svg class="blh-gota" viewBox="0 0 200 250" role="img" aria-label="Uma gota de leite dourada" focusable="false">
                <path fill="#e8a317" d="M100 6C100 6 22 98 22 158a78 78 0 0 0 156 0C178 98 100 6 100 6Z"/>
                <path fill="none" stroke="#fff" stroke-opacity=".55" stroke-width="9" stroke-linecap="round" d="M52 160c0 24 14 44 38 52"/>
            </svg>
        </div>
    </section>

    <div class="blh-wrap">
        <section class="blh-secao" aria-labelledby="titulo-passos">
            <h2 id="titulo-passos">Como funciona</h2>
            <ol class="blh-passos">
                <li>
                    <h3>Você envia seus dados</h3>
                    <p>Preencha o formulário, sem conta e sem senha. Você escolhe qual número recebe WhatsApp.</p>
                </li>
                <li>
                    <h3>A equipe entra em contato</h3>
                    <p>Uma pessoa do Banco confere o pedido e chama você pelo WhatsApp.</p>
                </li>
                <li>
                    <h3>Combinamos uma visita</h3>
                    <p>A visita pode ser no Banco de Leite ou na sua casa, no horário que a equipe combinar com você.</p>
                </li>
                <li>
                    <h3>A equipe avalia</h3>
                    <p>Depois da visita, a equipe analisa as informações e, se for preciso, pede documentos ou exames.</p>
                </li>
            </ol>
        </section>

        <section class="blh-secao" aria-labelledby="titulo-preparo">
            <h2 id="titulo-preparo">Antes de começar</h2>
            <div class="blh-preparo">
                <div class="blh-preparo__bloco">
                    <h3>Tenha à mão</h3>
                    <ul>
                        <li>Nome completo e CPF</li>
                        <li>Telefone com DDD e e-mail</li>
                        <li>Endereço completo, com CEP</li>
                    </ul>
                </div>
                <div class="blh-preparo__bloco blh-preparo__bloco--aviso">
                    <h3>O envio não garante a doação</h3>
                    <p>Receber sua solicitação não significa aprovação para doar nem visita marcada.</p>
                    <p>Não envie exames, documentos ou dados de outras pessoas no formulário.</p>
                </div>
            </div>
            <p class="blh-acao"><a class="btn btn-blh btn-lg" href="{{ route('solicitacoes.create') }}">Quero doar leite humano</a></p>
        </section>
    </div>
</x-public-layout>
