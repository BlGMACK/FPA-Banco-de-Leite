# Arquitetura

## Estado implementado

A aplicação é um monólito web Laravel com páginas renderizadas por Blade e autenticação por sessão. Não há rotas de API de negócio no repositório; a preferência por JSON em certos erros em [bootstrap/app.php](../bootstrap/app.php) não cria uma API. Conferido em 29/10/2026.

| Parte | Evidência atual |
| --- | --- |
| Backend | PHP, Laravel 13, Eloquent; [composer.json](../composer.json) e [composer.lock](../composer.lock). O lock fixa Laravel 13.34.0. |
| Autenticação | Fluxos derivados do Breeze, sem autocadastro; controllers em [Auth](../app/Http/Controllers/Auth), guard web em [config/auth.php](../config/auth.php). |
| Persistência | MySQL/MariaDB no [.env.example](../.env.example); estrutura definida pelas migrations. Testes em SQLite `:memory:` e, por seleção, em MariaDB exclusivo. |
| Frontend | Blade, Bootstrap 5, Alpine e Popper; [package.json](../package.json). |
| Assets | Vite 8 e plugin Laravel; [vite.config.js](../vite.config.js). Versões exatas no [lock npm](../package-lock.json). |
| Testes e ferramentas | PHPUnit, Pint e Boost nas dependências de desenvolvimento. Não há CI configurado. |

## Organização e responsabilidades

| Caminho | Responsabilidade observada |
| --- | --- |
| [routes/web.php](../routes/web.php) | Solicitação pública sem conta (`/doar`), painel e perfil, e áreas internas: `interno/solicitacoes`, `interno/acompanhamentos`, `interno/agenda`, `interno/relatorios`, `interno/funcionarios` e `interno/permissoes`. Cada grupo exige autenticação e o Gate correspondente. |
| [routes/auth.php](../routes/auth.php) | Sessão, senha e verificação de e-mail para contas internas. Sem autocadastro. |
| [routes/console.php](../routes/console.php) | Comando de exemplo `inspire`; não há agendamento de negócio. |
| [bootstrap/app.php](../bootstrap/app.php) | Inicialização, health check `/up`, `EnsureInternalAccount` no grupo web e alias `role`. |
| [app/Http/Controllers](../app/Http/Controllers) | `SolicitacaoController`, `AtendimentoSolicitacaoController`, `AgendaController`, `RelatorioVisitaController`, `FuncionarioController`, `PermissaoController`, `ProfileController` e controllers de autenticação. Selecionam entradas, delegam regras aos models e retornam views ou redirecionamentos. |
| [app/Http/Requests](../app/Http/Requests) | Form Requests de login, perfil, solicitação, atendimento e criação de funcionário. Agenda e relatórios validam no controller e nos métodos dos models. |
| [app/Http/Middleware](../app/Http/Middleware) | `EnsureInternalAccount` (revalida a conta a cada requisição) e `EnsureUserHasRole` (registrado como `role`, sem uso atual em rotas). |
| [app/Models](../app/Models) | `User` e quinze models de domínio: identidade e pedidos (`Interessada`, `Solicitacao`, `ContatoSolicitacao`, `ResolucaoSolicitacao`, `EventoSolicitacao`), permissões (`Permissao`, `ConcessaoPermissao`, `EventoPermissao`), agenda (`Agendamento`, `ReservaAgenda`, `EventoAgendamento`) e visita (`Atendimento`, `RelatorioVisita`, `VersaoRelatorioVisita`, `EventoAtendimento`). |
| [AppServiceProvider](../app/Providers/AppServiceProvider.php) | Gates `atender-solicitacoes`, `gerenciar-agenda`, `criar-funcionarios`, `consultar-relatorios-visita`, `registrar-relatorios-visita` e `gerenciar-permissoes`. O admin é liberado explicitamente em cada capacidade, sem bypass global. |
| [database](../database) | Migrations, factories e seeders (`DatabaseSeeder` de demonstração e `PermissaoSeeder` do catálogo); detalhes em [Modelagem](backend/MODELAGEM.md). |
| [resources/views](../resources/views) | Layouts, componentes, autenticação, perfil e as áreas `solicitacoes`, `agenda`, `relatorios`, `funcionarios` e `permissoes`, além de `errors/409`. `welcome.blade.php` é remanescente do scaffold, sem rota. |
| [app/View/Components](../app/View/Components) | Componentes de layout Blade. |
| [resources/css/app.css](../resources/css/app.css) e [resources/js/app.js](../resources/js/app.js) | Bootstrap, estilos e interações Alpine/modal. |
| [config](../config) | Configurações da aplicação; variáveis locais ficam fora do Git. |
| [public](../public) e [storage](../storage) | Entrada pública/assets e arquivos gerados em execução. |
| [tests](../tests) | Testes de schema, models, autorização, operações, concorrência e fluxo HTTP por módulo, em [Feature](../tests/Feature). |

## Backend e frontend

O navegador recebe HTML, envia formulários com proteção CSRF e acompanha redirecionamentos. O backend autentica, valida, verifica permissões e persiste. O frontend exibe valores, erros e mensagens de sessão e oferece interações locais. Os detalhes do intercâmbio estão em [Contratos](backend/CONTRATOS.md).

**Convenção observada:** operações de negócio ficam em métodos dos models (por exemplo `ResolucaoSolicitacao`, `Agendamento`, `Atendimento::registrar` e `RelatorioVisita::finalizar`). Esses métodos autorizam, validam estado e versão, gravam em transação com trava e registram evento com autoria; o controller apenas seleciona entradas e traduz erros (422/409) para os formulários. Não foram criadas camadas de serviços, repositórios ou API.

Esconder links na navegação não substitui a autorização do servidor. As rotas usam autenticação e Gates, e a navegação usa as mesmas capacidades. Login e recuperação de senha aceitam somente `admin` e `funcionario`. `EnsureInternalAccount` reconsulta a existência e o papel da conta e encerra sessões sem conta interna válida. `FuncionarioController` cria somente funcionários com senha aleatória não divulgada e autoria; a primeira senha é definida pela recuperação por e-mail.

### Módulos

- **Recebimento e fila:** `SolicitacaoController`, `StoreSolicitacaoRequest` e views `solicitacoes`. Correspondências são calculadas no model `Solicitacao`, sem flags persistidas. Ver [Contratos](backend/CONTRATOS.md#recebimento-e-fila-implementados-em-24102026).
- **Atendimento administrativo:** `AtendimentoSolicitacaoController` e `AtendimentoSolicitacaoRequest`; decisões transacionais em `ResolucaoSolicitacao`. A área de acompanhamento lista admitidas e o estado da visita. Ver [Contratos](backend/CONTRATOS.md#atendimento-administrativo-implementado-em-26102026).
- **Agenda:** `AgendaController` e views `agenda`, protegidos por `gerenciar-agenda`, independente de atendimento. Operações em `Agendamento` e `ContatoSolicitacao::registrarAcompanhamento`, com bloqueio de sobreposição do responsável. As views mostram apenas identificação e contato necessários à visita. Ver [Contratos](backend/CONTRATOS.md#fluxo-web-da-agenda-implementado-em-27102026).
- **Relatórios de visita:** `RelatorioVisitaController` e views `relatorios`, protegidos por `consultar-relatorios-visita` e, nas escritas, `registrar-relatorios-visita`. Operações em `Atendimento` e `RelatorioVisita`; leitura recebe somente versões publicadas. Ver [Contratos](backend/CONTRATOS.md#interface-blade-implementada-em-29102026).

- **Permissões:** `PermissaoController` e views `permissoes`, protegidos por `gerenciar-permissoes` (somente admin). Concessão e revogação delegadas a `Permissao::conceder/revogar`, com motivo e histórico. Ver [Contratos](backend/CONTRATOS.md#gestão-de-permissões-implementada-em-29102026).

A página inicial apresenta o acesso à solicitação e ao login; o painel apenas confirma a sessão. Todas as telas existentes são apoio ao desenvolvimento, sem aprovação como interface final nem revisão visual registrada.
