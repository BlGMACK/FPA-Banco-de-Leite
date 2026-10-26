# Status do backend

Levantamento inicial de 22/10/2026, acrescido da preparação do schema administrativo em 24/10/2026. **Implementado** indica presença no código; **validado** exige evidência de execução identificada. Este levantamento não homologou o sistema.

## Implementado

| Área | O que existe | Fonte |
| --- | --- | --- |
| Sessão | Login, logout, regeneração de sessão e limitação por e-mail/IP após cinco falhas de login. | [LoginRequest](../../app/Http/Requests/Auth/LoginRequest.php), [controller](../../app/Http/Controllers/Auth/AuthenticatedSessionController.php). |
| Cadastro | Nome, e-mail e senha; autenticação após cadastro. Papel padrão do banco é doadora; o formulário não escolhe papel. | [controller](../../app/Http/Controllers/Auth/RegisteredUserController.php), [migration de papel](../../database/migrations/2026_10_04_000001_add_role_to_users_table.php). |
| Perfil | Consulta, edição de nome/e-mail, troca de senha e exclusão com senha atual; conta com vínculos é preservada, com erro no formulário e sessão mantida. | [ProfileController](../../app/Http/Controllers/ProfileController.php), [ProfileUpdateRequest](../../app/Http/Requests/ProfileUpdateRequest.php), [PasswordController](../../app/Http/Controllers/Auth/PasswordController.php). |
| Recuperação de senha | Solicitação e redefinição por token usando o broker Laravel. | [rotas](../../routes/auth.php), [controllers](../../app/Http/Controllers/Auth). |
| E-mail | Rotas e lógica de verificação presentes; obrigatoriedade não ativada no model. | [User](../../app/Models/User.php), [rotas](../../routes/auth.php). |
| Acesso por papel | Admin, funcionário e doadora; demonstrações protegidas por middleware. | [middleware](../../app/Http/Middleware/EnsureUserHasRole.php), [rotas web](../../routes/web.php). |
| Persistência | Contas e tabelas de infraestrutura; MySQL no exemplo compartilhado. | [Modelagem](MODELAGEM.md), [.env.example](../../.env.example). |
| Testes | Autenticação, cadastro, senha, verificação e perfil, mais testes de exemplo. | [Feature](../../tests/Feature), [Unit](../../tests/Unit), [configuração](../../phpunit.xml). |
| Schema administrativo | Seis migrations para onze tabelas: identidade, pedidos, contatos, resoluções, histórico e permissões. Aplicadas ao banco de desenvolvimento após autorização; evidência abaixo. | [Recorte físico](MODELAGEM.md#recorte-administrativo-preparado-em-24102026). |
| Models e autorização administrativa | Oito models com relações/casts e factories; Gates de atendimento e gestão, concessão/revogação com autoria e transação. Ainda sem interface de gestão de permissões. | [Contrato implementado](CONTRATOS.md#base-de-models-e-autorização-implementada-em-24102026). |
| Recebimento e fila | Formulário público/interno, confirmação neutra, token de operação, limitação de envios, fila paginada e detalhe com correspondências atuais restritas à equipe autorizada. | [Recorte implementado](CONTRATOS.md#recebimento-e-fila-implementados-em-24102026). |
| Atendimento administrativo | Início de análise, contatos, conferências, arquivamento de repetidos, admissão e acompanhamento inicial; decisões com versões, transação, autoria e histórico. | [Contrato efetivo](CONTRATOS.md#atendimento-administrativo-implementado-em-26102026). |

O model não implementa `MustVerifyEmail`: a presença do middleware `verified` no painel não torna a verificação obrigatória no estado atual.

## Evidências e seus limites

As execuções abaixo ocorreram **anteriormente nesta mesma sessão de preparação local**, em 22/10/2026. Não foram repetidas nesta tarefa documental e não há relatório versionado de CI.

| Estado | Evidência anterior | Limite |
| --- | --- | --- |
| Validado localmente | PHP 8.5.1; suíte retornou 25 testes aprovados e 61 assertions, usando SQLite em memória. | Antes da troca local para MySQL; não comprova matriz de papéis, MySQL ou navegador. |
| Validado localmente | Build Vite 8.3.5 concluído após correções de dependências; Composer validou o manifesto e os requisitos de plataforma naquele momento. | Não é auditoria contínua nem revisão visual. |
| Validado localmente | Após a mudança para MySQL, quatro migrations aplicadas e três contas fictícias listadas em `banco_de_leite_dev.users`. | Ambiente XAMPP local; sem teste automatizado de integração MySQL. |
| Validado localmente | Após a mudança para MySQL, respostas HTTP 200 em início, login e cadastro. | Verificação de resposta, sem interação ou login completo em navegador. |
| Verificado nesta tarefa | Leitura das fontes e comparação com os documentos. | Inspeção estática; verificações documentais finais são informadas na entrega. |

## Falta validar ou decidir

- Matriz completa de papéis com acessos permitidos/negados: não há teste específico encontrado para `EnsureUserHasRole`; condições da navegação não são evidência de autorização testada.
- Cadastro/perfil não aceitam alteração pública de papel; tentativas de escalada e papel padrão cobertos em `EmployeeAuthorizationTest`. Fluxo administrativo de criação/alteração de contas permanece pendente.
- Testes atuais de notificações usam fakes; entrega SMTP real e fluxos externos não foram validados.
- Modais, navegação móvel, acessibilidade e apresentação final precisam de validação visual.
- Rotas de apoio estão acessíveis conforme middleware; decidir sua permanência fora do desenvolvimento.
- Seeder não idempotente e scripts Composer ainda com geração de chave/menção a SQLite: revisar em tarefa de código autorizada.
- Antes de disponibilizar atendimento: ligar os Gates às futuras rotas, implementar validações e transações das solicitações. A proteção de exclusão por histórico já está tratada; não equivale a desativação de conta ou autorização clínica.

## Verificações de 24/10/2026 — schema administrativo

Ambiente: PHP 8.5.10, Laravel 13.34.0, PHPUnit 12.5.37. Os processos de teste receberam explicitamente `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vazio e FKs habilitadas. Não foi alterado `.env`.

| Verificação executada | Resultado e limites |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/AdministrativeSchemaTest.php` | 20 testes aprovados, 34 assertions. Exercita duplicidade permitida de dados, unicidade das operações/protocolos, FKs, histórico, permissões e rollback/reaplicação do recorte preservando contas preexistentes em memória. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado para os arquivos PHP alterados. |
| `php vendor/bin/phpunit` | 45 testes aprovados, 95 assertions, após formatação. Inclui os 25 testes anteriores; não comprova MySQL, navegador, permissões nas futuras rotas ou concorrência real entre operadores. |

O banco `banco_de_leite_dev` foi consultado apenas para metadados/status. Nenhuma migration/seed foi aplicada nele. A verificação adicional de MariaDB está registrada abaixo; concorrência, consultas de correspondência e comportamento sob volume real continuam pendentes.

### Integração MariaDB em banco exclusivo — 24/10/2026

Após autorização do usuário, foi criado `banco_de_leite_schema_test`, inicialmente inexistente, no servidor local **MariaDB 10.4.32**, com charset `utf8mb4` e collation `utf8mb4_unicode_ci`. A execução usou a conexão `mysql` do Laravel e variáveis somente no processo: `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test`, `DB_URL` vazio. Nenhuma credencial ou configuração de `.env` foi alterada.

- `php vendor/bin/phpunit tests/Feature/AdministrativeSchemaTest.php`: **20 testes aprovados, 34 assertions** no MariaDB. Aplicação das migrations, restrições, preservação de autoria e rollback/reaplicação do recorte foram exercitados com dados fictícios.
- O teste de schema passou a usar `DatabaseMigrations`, pois DDL no MySQL/MariaDB pode realizar commit implícito. A recriação e o rollback automáticos ficam limitados ao banco descartável; uma guarda anterior às migrations aceita somente SQLite em memória ou o banco local com o nome exato acima, em `testing`, sem URL externa nem configuração em cache.
- Após o ajuste, `php vendor/bin/phpunit` em SQLite `:memory:`: **45 testes aprovados, 95 assertions**. `php vendor/bin/pint --dirty --format agent`: aprovado.

O banco de testes foi mantido para futuras execuções; o teardown reverte as tabelas da aplicação. A inspeção final confirmou somente a tabela `migrations`, sem registros. Esta evidência vale para a versão local do MariaDB, não comprova MySQL 8, concorrência entre operadores, performance nem regras de aplicação ainda não implementadas. A consulta final de `migrate:status` no banco de desenvolvimento confirmou as quatro migrations anteriores aplicadas e as seis novas pendentes.

## Verificações de models e autorização — 24/10/2026

- Suíte completa em SQLite `:memory:`, com `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vazio e FKs habilitadas: `php vendor/bin/phpunit`, **72 testes e 224 assertions aprovados**. São 27 casos adicionais aos 45 anteriores.
- `php vendor/bin/pint --dirty --format agent`: aprovado. Testes novos em `AdministrativeModelsTest` e `EmployeeAuthorizationTest`; ampliado `ProfileTest`. Relações/casts, matriz de acesso, auditoria transacional e preservação de autoria cobertos; detalhes em [Segurança, E-03](../SEGURANCA.md#e-03--models-permissões-e-exclusão-com-histórico-24102026).
- Tentativa de execução das três classes no MariaDB exclusivo: **32 erros de conexão recusada, zero assertions**, com servidor local indisponível em `127.0.0.1:3306`. Essa primeira tentativa não validou as operações; a reexecução bem-sucedida está registrada abaixo. Concorrência real permanece pendente.
- Sem alteração do `.env`, migrations/seeders no desenvolvimento ou novas dependências. As novas tabelas continuam exigindo aplicação autorizada antes de uso local; as rotas atuais não foram convertidas para o fluxo de pedidos.

### Reexecução MariaDB de models e autorização — 24/10/2026

Após ativação do servidor e solicitação do usuário, `php vendor/bin/phpunit tests/Feature/AdministrativeModelsTest.php tests/Feature/EmployeeAuthorizationTest.php tests/Feature/ProfileTest.php` passou com **32 testes e 150 assertions** no MariaDB 10.4.32, usando exclusivamente `banco_de_leite_schema_test`. Variáveis de processo: `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test` e `DB_URL` vazio. Sem mudanças de código ou `.env`.

A inspeção final confirmou 20 tabelas, 10 migrations e zero registros de aplicação no banco de testes. A estrutura permanece disponível; os dados fictícios foram revertidos pelas transações dos testes. No banco de desenvolvimento, `migrate:status` confirmou as quatro migrations anteriores aplicadas e seis novas pendentes. Detalhes e limites em [Segurança, E-04](../SEGURANCA.md#e-04--reexecução-dos-models-e-autorização-no-mariadb-24102026). Concorrência real e futuros endpoints continuam fora desta evidência.

## Verificações de recebimento e fila — 24/10/2026

- `php vendor/bin/phpunit tests/Feature/SolicitacaoFlowTest.php`: **36 testes, 233 assertions aprovados** no MariaDB 10.4.32 exclusivo `banco_de_leite_schema_test`, com `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test` e `DB_URL` vazio.
- `php vendor/bin/phpunit`: **108 testes, 457 assertions aprovados** em SQLite `:memory:`, com ambiente testing, URL vazia e FKs habilitadas, após os ajustes finais. `php vendor/bin/pint --dirty --format agent`: aprovado.
- Cobertura e limites em [Segurança, E-05](../SEGURANCA.md#e-05--recebimento-fila-e-correspondências-24102026). Sem revisão visual em navegador, teste de carga ou concorrência real. Não houve alteração de dependências, CSS/JS, `.env` ou aplicação de migrations/seeders no banco de desenvolvimento.
- Para uso local das rotas novas e da navegação de funcionários, o schema administrativo precisa estar aplicado e as concessões preparadas por operação autorizada. Esta entrega não fez essa ativação.

## Ativação local — 24/10/2026

Após autorização do usuário, foram executados `php artisan migrate --no-interaction` e `php artisan db:seed --class=PermissaoSeeder --no-interaction` em `banco_de_leite_dev`. As seis migrations administrativas foram aplicadas no lote 3; `migrate:status` confirmou as dez migrations aplicadas. Não houve recriação do banco, execução de `DatabaseSeeder` ou mudança de `.env`.

A consulta final confirmou três contas preservadas, um item no catálogo de permissões, zero concessões a funcionários e zero solicitações. Administradores podem acessar o módulo conforme o Gate existente; funcionários dependem de concessão explícita.

Um servidor temporário conectado exclusivamente a `banco_de_leite_schema_test` retornou HTTP 200 em `/doar` e `/pedido-recebido`, e 302 na fila interna sem autenticação. Não foram inseridos dados por essa verificação. A revisão visual não pôde ser realizada: a ferramenta informou que o navegador não estava disponível e listou zero navegadores. Layout, interação autenticada e responsividade continuam pendentes. Esses checks HTTP não substituem os testes funcionais anteriores nem a homologação visual. Evidência de segurança: [E-06](../SEGURANCA.md#e-06--ativação-local-24102026).

As seções anteriores registram o estado na ocasião de cada verificação; suas referências a migrations pendentes foram superadas por esta ativação.

## Não implementado no código analisado

**Atualização posterior:** o schema da agenda foi preparado e verificado após autorização; veja as evidências abaixo. A especificação inicial citada a seguir foi superada apenas quanto à preparação física. Fluxo web, models e autorização de agenda continuam pendentes.

**Especificação posterior em 26/10/2026:** detalhados o [schema proposto da agenda](MODELAGEM.md#desenho-físico-proposto-para-agenda--26102026) e a [autorização/rotas propostas](CONTRATOS.md#contrato-técnico-proposto-da-agenda--26102026). Conferidos no código o contexto atual de contatos, o acesso ao acompanhamento e a restrição do catálogo a atendimento. Alterações apenas documentais nesta especificação; sem testes de aplicação, migrations ou seeders executados. A agenda continua não implementada.

As evidências da entrega administrativa estão na seção seguinte. Implementação não representa homologação visual ou clínica.

Recebimento, fila, contatos, conferências, resolução de repetições, admissão e acompanhamento administrativo inicial estão implementados. Permanecem pendentes correção de dados do pedido, agendamento/relatório de visita, anexos e acompanhamento clínico, coleta, recebimento de leite, processamento, estoque, distribuição e relatórios. Não há API REST de negócio.

Backend futuro e dependências: [Roadmap](ROADMAP.md). Telas existentes e seus limites: [Arquitetura](../ARQUITETURA.md). Detalhes de integração: [Contratos](CONTRATOS.md).

## Verificações de atendimento administrativo — 26/10/2026

**Retorno manual do usuário em 26/10/2026:** informou ter testado o site e que estava funcionando bem, autorizando avançar. Não foram informados cenários, navegador, dispositivos ou matriz de permissões cobertos. Registrar como teste manual relatado do fluxo atual, sem inferir homologação clínica, acessibilidade, responsividade completa ou concorrência.

Entrega autorizada: início da análise, contatos, conferências, resolução de repetições, admissão administrativa e consulta inicial de acompanhamento. Não houve novas migrations, dependências, alterações de `.env`, seeders ou gravações no banco de desenvolvimento nesta etapa. Correção de dados por PATCH permanece pendente.

| Verificação executada após ajustes finais | Resultado |
| --- | --- |
| `php vendor/bin/phpunit` com `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:` e `DB_URL` vazio | **144 testes, 650 assertions aprovados**. |
| `php vendor/bin/phpunit tests/Feature/AtendimentoSolicitacaoTest.php` com `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test` e `DB_URL` vazio | **36 testes, 193 assertions aprovados** no MariaDB local isolado. A classe usa a guarda de banco e transações de teste. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado após a formatação dos arquivos PHP alterados. |
| `php artisan route:list --path=interno --no-interaction` | Rotas administrativas registradas, incluindo as cinco escritas e lista/detalhe de acompanhamento. |

Foram testados conflitos sequenciais entre versões, repetição sem duplicar decisão, bloqueio de contatos insuficientes, revisão de correspondências, revogação de permissão, CSRF ativo e reversão integral quando a auditoria falha. A admissão não cria conta nem aprovação clínica. Detalhes em [E-07](../SEGURANCA.md#e-07--atendimento-e-decisões-administrativas-26102026).

Limites: sem teste com conexões simultâneas, carga ou navegador; Blade foi renderizado nos testes, mas a interação Alpine, responsividade e acessibilidade não foram homologadas. A próxima etapa é revisar as telas com a equipe e especificar agendamento/visita e relatório narrativo, incluindo responsáveis, estados e critérios antes da implementação clínica.

## Verificações do schema de agenda — 26/10/2026

Após autorização do usuário, quatro migrations aditivas foram preparadas: contexto/operação dos contatos, agendamentos, reservas e eventos. Fontes em [Modelagem](MODELAGEM.md#desenho-físico-proposto-para-agenda--26102026). Não foram alteradas migrations anteriores. `AdministrativeSchemaTest` foi adaptado para remover/reaplicar primeiro as dependências de agenda ao exercitar rollback do recorte administrativo; o cenário de preservação de contas permanece.

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/AgendaSchemaTest.php tests/Feature/AdministrativeSchemaTest.php` em SQLite `:memory:` | **36 testes, 60 assertions aprovados**. |
| Mesmo comando na conexão `mysql`, banco exclusivo `banco_de_leite_schema_test` | **36 testes, 60 assertions aprovados** no MariaDB local. |
| `php vendor/bin/phpunit` em SQLite `:memory:` | **160 testes, 676 assertions aprovados**, incluindo os 16 novos casos de schema da agenda. |
| `php vendor/bin/pint --dirty --format agent` e `git diff --check` | Aprovados. |
| `php artisan migrate:status --no-interaction` no desenvolvimento | Dez migrations anteriores aplicadas e quatro novas pendentes. Nenhuma migration ou seeder aplicada ao desenvolvimento nesta etapa. |

Os processos de teste receberam `APP_ENV=testing`, `DB_CONNECTION`/`DB_DATABASE` explícitos e `DB_URL` vazio, sem alterar `.env`. `AgendaSchemaTest` usa `DatabaseMigrations`, com guarda do ambiente/banco antes da preparação; os dados são fictícios. Foi corrigido o rollback da tabela com autorreferência: remover sua FK de substituição antes de derrubar a tabela permite o teardown em SQLite com histórico de reagendamento.

Cobertura e limites em [E-08](../SEGURANCA.md#e-08--schema-da-agenda-26102026). A base física não disponibiliza agenda no site: ainda faltam models, catálogo/Gates/concessões, validações, operações transacionais e telas. Não foram testados concorrência real entre conexões, sobreposição de responsáveis, fuso na interface ou regras clínicas. O banco de testes é descartável; rollback de schema com dados reais seria destrutivo e não está autorizado por esses testes.
