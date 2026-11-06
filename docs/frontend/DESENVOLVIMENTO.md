# Desenvolvimento do frontend

Guia para a equipe de frontend e seus agentes de IA. Define como trabalhar **tela por tela**, quais diretórios cada tarefa pode alterar e o que precisa ser preservado para não quebrar o backend.

**Origem:** o trabalho tela por tela, a branch própria de frontend, os pull requests e o merge feito pelo responsável do repositório foram definidos pelo usuário em 30/10/2026. A branch por tela, a tela 0 de base visual, a ordem sugerida e o checklist são complementos **propostos** nesta redação, a confirmar.

## O que é o frontend neste projeto

Não existe SPA nem API. O frontend é o conjunto de **views Blade** renderizadas pelo Laravel, estilizadas com **Bootstrap 5.3** e com interações leves em **Alpine**, empacotadas pelo **Vite**. Cada tela recebe dados prontos do controller e envia formulários HTML comuns (com CSRF) de volta ao servidor.

Todas as telas atuais são **de apoio ao desenvolvimento**: funcionam e são testadas, mas não têm aprovação visual. O trabalho do frontend é transformá-las na interface final **sem mudar o comportamento**.

## Fluxo de branches e pull requests

```
main ──────────────────────────────●─────────────── (backend + merges do frontend)
  └─ frontend ───●────●────●───────┘   (integração; PR frontend → main pelo responsável)
        └─ front/login ─┘    │
             └─ front/fila-solicitacoes ─┘
```

1. A branch **`frontend`** nasce de `main` e é a integração do trabalho visual. Só o responsável faz merge nela.
2. Cada tela é uma branch curta criada a partir de `frontend`: `front/<tela>` (nomes na tabela de telas abaixo).
3. Ao concluir a tela, abra um **pull request de `front/<tela>` para `frontend`**, com o checklist preenchido. O responsável revisa e faz o merge.
4. Periodicamente, o responsável abre um PR de **`frontend` para `main`**.
5. Quando `main` receber mudanças de backend, o responsável atualiza `frontend` com `main` (merge `main` → `frontend`). Antes de abrir PR, atualize sua branch de tela com `frontend`.

Regras:

- **Uma tela por branch e por PR.** Se encontrar um problema em outra tela, anote no PR; não corrija ali.
- Commits pequenos, com mensagem em português descrevendo a finalidade, por exemplo `style: reorganiza filtros da fila de solicitações`.
- Não faça merge da própria PR nem push direto em `frontend` ou `main`.
- Telas que dependem de componentes compartilhados só devem começar **depois do merge da tela 0**, para evitar conflitos.

## Mapa de diretórios

Esta seção existe para que pessoas e agentes **não percorram o projeto inteiro**. Leia e altere apenas o necessário para a tela da tarefa.

### Pode alterar

| Caminho | Quando |
| --- | --- |
| `resources/views/<área>/` | A pasta da tela da tarefa (ver tabela de telas). |
| `resources/views/components/` | **Somente na tela 0** ou com autorização no PR, pois afeta todas as telas. |
| `resources/views/layouts/` | **Somente na tela 0** (layout, navegação, página de visitante). |
| `resources/css/app.css` | Estilos próprios. Prefira classes do Bootstrap; use o prefixo `blh-` em classes novas. |
| `resources/js/app.js` | Interações locais. Prefira Alpine inline na view; mexa aqui só para comportamento compartilhado. |
| `tests/Feature/<Módulo>WebTest.php` | Somente para atualizar **um texto verificado** que a tela mudou de propósito (ver “Textos verificados por testes”). |
| `docs/frontend/` | Atualizar o progresso por tela neste documento. |

### Pode ler, sem alterar

| Caminho | Para quê |
| --- | --- |
| O controller da tela (coluna “Controller” da tabela) | Ver quais variáveis chegam à view e para onde o formulário envia. Leia só o método da tela. |
| `routes/web.php` e `routes/auth.php` | Conferir nomes de rotas usados em `route(...)`. |
| O teste web do módulo | Saber quais textos e atributos são verificados. |
| [Contratos](../backend/CONTRATOS.md), seção da área | Regras de exibição e permissões da tela. |
| [Ambiente](../AMBIENTE.md) | Instalar, rodar e compilar. |

### Não alterar

`app/`, `database/`, `routes/`, `config/`, `bootstrap/`, `lang/`, `public/` (exceto imagens novas autorizadas), `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `vite.config.js`, `.env` e os demais documentos de `docs/`.

Se a tela precisar de um dado que a view não recebe, de uma rota nova, de uma dependência npm nova ou de mudança de regra, **não implemente**: descreva a necessidade no PR para o backend avaliar.

### Não ler

Para uma tarefa de tela, não é preciso abrir `app/Models/`, `database/migrations/`, os demais testes nem `docs/backend/MODELAGEM.md`, `STATUS.md` e `ROADMAP.md`. São grandes e tratam de regra de negócio, não de interface.

## Telas

Rotas internas exigem login. A coluna “Permissão” indica quem enxerga a tela; o admin enxerga todas.

| # | Branch | Tela | URL | View(s) | Controller (leitura) | Teste a rodar | Permissão |
| --- | --- | --- | --- | --- | --- | --- | --- |
| 0 | `front/base` | Base visual: layout interno, navegação, layout de visitante, componentes | todas | `layouts/`, `components/`, `css/app.css`, `js/app.js` | `app/View/Components/` | suíte inteira | — |
| 1 | `front/inicio` | Página inicial pública | `/` | `inicio.blade.php` | closure em `routes/web.php` | `InternalAccessTest` | pública |
| 2 | `front/login` | Login, recuperação e redefinição de senha, confirmação e verificação de e-mail | `/login`, `/forgot-password`, `/reset-password/{token}`, `/confirm-password`, `/verify-email` | `auth/` | `Auth/` | `tests/Feature/Auth`, `InternalAccessTest` | pública |
| 3 | `front/solicitacao-publica` | Formulário público de doação e confirmação | `/doar`, `/pedido-recebido` | `solicitacoes/create`, `solicitacoes/recebida` | `SolicitacaoController@create/recebida` | `SolicitacaoFlowTest` | pública |
| 4 | `front/painel` | Painel após login | `/dashboard` | `dashboard.blade.php` | closure em `routes/web.php` | `InternalAccessTest` | logado |
| 5 | `front/fila-solicitacoes` | Fila e detalhe de solicitações com atendimento | `/interno/solicitacoes`, `/interno/solicitacoes/{id}` | `solicitacoes/index`, `solicitacoes/show`, `solicitacoes/atendimento` | `SolicitacaoController@index/show` | `SolicitacaoFlowTest`, `AtendimentoSolicitacaoTest` | `atender-solicitacoes` |
| 6 | `front/acompanhamentos` | Pedidos admitidos e estado da visita | `/interno/acompanhamentos`, `/interno/acompanhamentos/{id}` | `solicitacoes/acompanhamentos`, `solicitacoes/acompanhamento` | `AtendimentoSolicitacaoController@index/show` | `AtendimentoSolicitacaoTest` | `atender-solicitacoes` |
| 7 | `front/agenda` | Agenda de visitas, agendamento, contatos e alterações | `/interno/agenda`, `/interno/agenda/acompanhamentos/{id}` | `agenda/` | `AgendaController` | `AgendaWebTest` | `gerenciar-agenda` |
| 8 | `front/relatorios` | Lista, registro da visita, relato, finalização e correção | `/interno/relatorios`, `/interno/relatorios/visitas/{id}`, `/interno/relatorios/{id}` | `relatorios/` | `RelatorioVisitaController` | `RelatorioVisitaWebTest` | `consultar-` / `registrar-relatorios-visita` |
| 9 | `front/funcionarios` | Cadastro de funcionário | `/interno/funcionarios/novo` | `funcionarios/` | `FuncionarioController` | `FuncionarioCreationTest` | `criar-funcionarios` |
| 10 | `front/permissoes` | Permissões e situação da conta | `/interno/permissoes`, `/interno/permissoes/{id}` | `permissoes/` | `PermissaoController` | `PermissaoWebTest`, `DesativacaoFuncionarioTest` | admin |
| 11 | `front/perfil` | Perfil, troca de senha e exclusão de conta | `/profile` | `profile/` | `ProfileController` | `ProfileTest`, `tests/Feature/Auth/PasswordUpdateTest.php` | logado |
| 12 | `front/erros` | Página de conflito (409) e páginas de erro | — | `errors/` | — | `RelatorioVisitaWebTest` | — |
| 13 | `front/avaliacoes` | Lista de avaliações, situação, decisão da análise do relatório e histórico | `/interno/avaliacoes`, `/interno/avaliacoes/{id}` | `avaliacoes/` | `AvaliacaoController` | `AvaliacaoWebTest` | `consultar-avaliacoes` / `avaliar-doadoras` |

Os caminhos de view são relativos a `resources/views/`. Os controllers ficam em `app/Http/Controllers/` e os testes em `tests/Feature/`.

**Ordem sugerida:** 0 primeiro, sozinha. Depois 1–4 (telas públicas e de entrada) e 5–13 em paralelo, uma pessoa por tela. `welcome.blade.php` é remanescente sem rota: não trabalhe nele.

## O que cada view precisa preservar

Mudar aparência é livre. Os itens abaixo são o **contrato** com o backend; alterá-los quebra formulários, testes ou a segurança.

- **Formulários:** `action="{{ route(...) }}"`, `method`, `@csrf` e `@method(...)` quando houver.
- **Nomes dos campos (`name`)** e seus valores. Não renomeie nem remova campos.
- **Campos ocultos de controle:** `versao` e `operacao_id`. Eles evitam gravação duplicada e sobrescrita entre duas pessoas; mantenha-os dentro do mesmo formulário.
- **Erros e valores antigos:** `@error`, `old(...)` e os *error bags* nomeados (`$errors->getBag('conta')`, `'contato'`, `'decisao'`, os bags dos formulários da agenda, `updatePassword`, `userDeletion`). Cada formulário mostra só os erros do seu bag.
- **Permissões na tela:** blocos `@can`/`@cannot`. Esconder um botão não protege nada (o servidor protege), mas mostrar um botão que a pessoa não pode usar confunde.
- **Escape:** use sempre `{{ }}`. Nunca use `{!! !!}` com dado vindo do banco ou do usuário.
- **Estados desabilitados e avisos:** por exemplo, “Finalizar texto salvo” fica desabilitado sem texto salvo, e “Desativar” fica desabilitado com visitas futuras. A lógica da condição fica; o visual pode mudar.
- **Mensagens de sessão:** `session('status')` deve continuar visível após redirecionamentos.
- **Acessibilidade já existente:** link “Pular para o conteúdo” (`href="#conteudo"`) e `<main id="conteudo">`, `label` ligado a cada campo, `role="alert"` em erros.
- **Datas:** a hora vem em UTC e as views convertem para `America/Sao_Paulo`. Mantenha a conversão ao reformatar.
- **Dados sensíveis:** não exiba mais dados pessoais do que a view já exibe. Agenda e relatórios mostram só o necessário à visita.

### Textos verificados por testes

Alguns textos e atributos são conferidos pelos testes web (`assertSee`), por exemplo “Visitas a registrar”, “Texto enviado e não salvo”, “Relatórios de visita” e `value="2030-01-02T09:00"`. Antes de mudar um título, rótulo ou botão, procure o texto no teste da tela.

- Se a mudança é intencional, atualize **apenas a string esperada** no teste e explique no PR.
- Nunca remova uma verificação de que algo **não** aparece (`assertDontSee`): ela protege dados sigilosos.

## Ambiente para trabalhar

Instalação e banco local seguem o [Ambiente](../AMBIENTE.md). Durante o trabalho:

```powershell
php artisan serve        # aplicação em http://127.0.0.1:8000
npm.cmd run dev          # outro terminal: recarrega CSS/JS
```

Para ter dados nas telas internas, crie registros fictícios pelo próprio sistema: envie uma solicitação em `/doar`, admita-a na fila, agende a visita e registre o relatório. Use apenas dados inventados.

As mensagens padrão do framework (validação, login, recuperação de senha, paginação) estão em português, em `lang/pt_BR/` e `lang/pt_BR.json` ([Status](../backend/STATUS.md#ativação-da-desativação-e-mensagens-em-pt-br--31102026)). Se uma mensagem aparecer em inglês, confira se o seu `.env` tem `APP_LOCALE=pt_BR`; tarefas de tela não alteram `lang/` nem `config/`.

## Checklist do pull request

Copie no corpo do PR e marque:

```markdown
## Tela
<número e nome da tela>

## O que mudou
- ...

## Capturas
<desktop (≥1200px) e celular (360px), antes e depois>

## Checklist
- [ ] Alterei somente arquivos permitidos para esta tela (`git diff --stat frontend`)
- [ ] Campos, rotas, `@csrf`, `versao`/`operacao_id`, error bags e `@can` preservados
- [ ] Sem `{!! !!}` com dados do banco ou do usuário
- [ ] Sem dependência nova (package.json / composer.json intactos)
- [ ] Funciona em 360px sem rolagem horizontal
- [ ] Navegável pelo teclado, com foco visível e rótulos nos campos
- [ ] Textos em português; textos verificados por testes conferidos
- [ ] `php artisan test --compact --filter=<Teste da tela>` passando
- [ ] `npm.cmd run build` sem erros
- [ ] Progresso atualizado em docs/frontend/DESENVOLVIMENTO.md

## Pendências para o backend
<dados, rotas ou regras que a tela precisaria e não existem; ou "nenhuma">
```

## Instruções para agentes de IA

Ao receber uma tarefa de tela, o agente deve:

1. Ler **este documento** e a linha da tela na tabela. Não é preciso ler o restante de `docs/` nem explorar o projeto.
2. Abrir somente: as views da tela, o método do controller da tela, o teste da tela e, se for a tela 0, `layouts/`, `components/`, `app.css` e `app.js`.
3. Alterar somente o que a seção “Pode alterar” permite e preservar a lista “O que cada view precisa preservar”.
4. Rodar o teste da tela e `npm.cmd run build`, e relatar o resultado real.
5. Não fazer commit, push ou merge sem pedido explícito, e não criar arquivos fora de `resources/views/`, `resources/css/`, `resources/js/` e `docs/frontend/`.

Instrução curta para colar no agente:

> Trabalhe apenas na tela N de docs/frontend/DESENVOLVIMENTO.md. Leia esse documento, as views, o controller e o teste listados na linha da tela, e nada além disso. Mude só a apresentação, preservando o contrato da seção “O que cada view precisa preservar”. Rode o teste da tela e o build, e descreva o resultado.

## Progresso por tela

Atualize esta tabela no próprio PR da tela.

| # | Tela | Situação | PR | Observações |
| --- | --- | --- | --- | --- |
| 0 | Base visual | em revisão (PR aberto) | — | Branch `frontend`: barra superior com usuário, menu lateral à direita a partir de 62rem (recolhido no botão “Menu” abaixo disso). |
| 1 | Página inicial | não iniciada | — | — |
| 2 | Login e senha | não iniciada | — | — |
| 3 | Solicitação pública | não iniciada | — | — |
| 4 | Painel | em revisão (PR aberto) | — | Branch `frontend`: cartões por área, exibidos conforme as permissões; aviso quando nenhuma área foi liberada. |
| 5 | Fila de solicitações | não iniciada | — | — |
| 6 | Acompanhamentos | não iniciada | — | — |
| 7 | Agenda | não iniciada | — | — |
| 8 | Relatórios de visita | não iniciada | — | — |
| 9 | Cadastro de funcionário | não iniciada | — | — |
| 10 | Permissões | não iniciada | — | — |
| 11 | Perfil | não iniciada | — | — |
| 12 | Páginas de erro | não iniciada | — | — |
| 13 | Avaliações | não iniciada | — | Tela de apoio entregue em 31/10/2026. |

Situações: não iniciada, em andamento, em revisão (PR aberto), integrada (merge em `frontend`), em `main`.

Novas telas do backend (por exemplo exames, quando forem implementados) entram nesta tabela com uma nova linha, depois que a view de apoio existir.
