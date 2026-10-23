# Adaptação do Breeze para Bootstrap

Referência da adaptação presente no código em 22/10/2026. Este documento complementa a documentação principal; não é um procedimento para aplicar ZIPs ou substituir arquivos do projeto.

## Implementado

- Layouts, navegação, autenticação, painel e perfil usam classes e componentes Bootstrap: [views](../resources/views).
- O CSS importa Bootstrap e contém estilos locais: [app.css](../resources/css/app.css).
- Bootstrap e Alpine são carregados pelo [app.js](../resources/js/app.js). Os eventos `open-modal` e `close-modal` acionam o modal de exclusão de conta.
- O [componente de modal](../resources/views/components/modal.blade.php) e o [formulário de exclusão](../resources/views/profile/partials/delete-user-form.blade.php) compartilham os atributos e eventos descritos em [Contratos](backend/CONTRATOS.md).
- As dependências atuais estão em [package.json](../package.json) e [package-lock.json](../package-lock.json); Tailwind não está declarado no manifesto atual.

A inspeção do código comprova a implementação, mas não a homologação visual. As telas de apoio ao desenvolvimento não representam a interface final. A organização e a separação entre backend e frontend estão em [Arquitetura](ARQUITETURA.md).

## Instalação e atualização

Siga [Ambiente](AMBIENTE.md), referência principal de requisitos, MySQL local, instalação pelos locks, execução e testes. Não reaplique ZIPs nem execute novamente o instalador do Breeze sobre as views adaptadas. Preserve o `.env`, a chave existente e os dados locais.

A preparação de CSS/JavaScript usa Vite. Comandos de build, modo de desenvolvimento e tratamento de `public/hot` estão centralizados em Ambiente. Alterações apenas visuais não exigem recriar o banco nem repetir o seeder.

## Checklist proposto de validação visual

Os itens abaixo são verificações a executar quando a interface for alterada; não são resultados de testes realizados nesta revisão documental. Use contas e dados fictícios.

- Login: conferir apresentação dos erros e fluxo de entrada/saída.
- Perfil: conferir salvamento, mensagens de sucesso e erros de validação.
- Senha: conferir erro de senha atual, confirmação e mensagem de sucesso.
- Exclusão: conferir abertura e cancelamento do modal e reabertura após senha incorreta. Confirmar exclusão somente com conta descartável destinada a esse teste.
- Navegação: conferir menu em tela estreita, teclado, foco e legibilidade.
- Cadastro e recuperação: conferir formulários e mensagens segundo os [contratos implementados](backend/CONTRATOS.md), sem assumir envio real de e-mail.

A matriz de acesso deve ser conferida também no servidor; esconder links não garante autorização. A referência dos papéis e da verificação de e-mail está em Contratos.

## Evidências e pendências

Consulte [Status do backend](backend/STATUS.md) para as validações locais anteriores e seus limites. Build concluído não comprova funcionamento do modal ou responsividade. Os resultados e bloqueios do antigo processo de aplicação por ZIP não representam o ambiente atual e não devem ser usados como validação desta versão.

Nesta atualização foram revisados o texto e as referências. Não foram executados build, testes de aplicação nem revisão em navegador. Mudanças de comportamento devem atualizar a documentação no mesmo pull request, conforme [Padrões](PADROES.md).
