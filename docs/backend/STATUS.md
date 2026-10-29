# Status do backend

## Ativação local dos relatórios — 29/10/2026

Com autorização do usuário para aplicar migrations/catálogo e fazer commits, executadas as cinco migrations `2026_10_28_183700` a `183704` com `php artisan migrate --path=...` (cinco caminhos explícitos) e `--no-interaction`. Executado somente `php artisan db:seed --class=PermissaoSeeder --no-interaction`. A primeira consulta falhou por conexão recusada, inclusive fora do sandbox; após o usuário iniciar o servidor, a aplicação concluiu normalmente.

`migrate:status --no-interaction` confirmou **20 migrations aplicadas, nenhuma pendente**, com as cinco novas no lote 6. Contagens antes/depois em `banco_de_leite_dev`: duas contas internas, uma solicitação e zero interessadas, contatos, agendamentos, reservas e eventos de agenda. As quatro tabelas novas estão vazias. O catálogo passou de três para cinco permissões; `permissao_user` continuou vazio. Códigos confirmados: atendimento, agenda, criação de funcionários, consulta de relatórios e registro de relatórios.

Não houve recriação do banco, `migrate:fresh`, `DatabaseSeeder`, inserção de fixtures, concessão automática ou mudança de `.env`/dependências. A verificação local foi de aplicação/status e contagens, sem exibir dados pessoais; não substitui revisão visual ou exercício manual. Os resultados automatizados da etapa anterior continuam registrados abaixo; sem alteração de PHP nesta ativação, não foram repetidos. `git diff --check` aprovado, com avisos somente de normalização de fim de linha.

O schema já permite usar a agenda atualizada. O backend de relatórios existe, mas suas telas/rotas continuam pendentes. A autorização atual de commit abrange as alterações da etapa anterior, separadas entre permissões, operações/integração e documentação; não inclui push.

## Operações e permissões de relatório — 28/10/2026

Implementados os Gates/catálogo de leitura e escrita, consultas autorizadas, realização da visita, salvamento de rascunho, finalização e abertura/finalização de correção. Agenda reconhece `realizado`, remove atendidas da fila e bloqueia nova confirmação nesse acompanhamento. Narrativas/motivos restritos não entram no histórico operacional. Contrato e limites em [Contratos](CONTRATOS.md#backend-implementado-após-autorização-em-28102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit`, SQLite `:memory:` | **291 testes: 287 aprovados, 4 ignorados; 1.270 assertions**. Os quatro ignorados exigem MariaDB para concorrência real (dois de agenda e dois de relatório). |
| `RelatorioVisitaOperationsTest`, `RelatorioVisitaAuthorizationTest`, `RelatorioVisitaConcurrencyTest`, `AgendaOperationsTest`, `AgendaWebTest`, MariaDB exclusivo, via `php vendor/bin/phpunit ... --stop-on-error` | **56 testes, 327 assertions aprovados**, antes dos oito cenários adicionais de revisão/auditoria/autorizações. |
| Reexecução final de `RelatorioVisitaOperationsTest` e `RelatorioVisitaAuthorizationTest`, SQLite e MariaDB exclusivo | **31 testes, 139 assertions aprovados em cada ambiente**, incluindo os oito casos adicionais. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado após ajustes automáticos. |

Os processos usam `APP_ENV=testing`, `DB_URL` vazio e conexão/banco explícitos: SQLite em memória ou `banco_de_leite_schema_test` local, com guarda antes da preparação. Concorrência usa processos/conexões independentes e barreira por trava: duas finalizações e cancelamento contra realização produziram uma conclusão e um 409 por disputa. Não é teste de carga nem prova de todas as interleavings. Falhas simuladas de auditoria confirmam reversão de realização e finalização. Testes cobrem datas/fuso, motivo de divergência, reenvio, revisão vencida, limites, revogação, matriz de acesso e não exposição na agenda.

Nenhuma migration/seeder executada no desenvolvimento, nenhuma concessão, alteração de `.env`, dependências ou commit nesta etapa. **A agenda atualizada exige as migrations de relatório antes do uso local**; ativação ainda pendente. Não há telas/rotas de relatório; não houve revisão visual. A proteção de versões por eventos Eloquent não impede SQL direto ou operações que ignorem eventos. Não foi implementada aprovação clínica.

## Models de relatório e validação MariaDB — 28/10/2026

O usuário autorizou repetir a validação, implementar os models e fazer commits por finalidade. Implementados `Atendimento`, `RelatorioVisita`, `VersaoRelatorioVisita` e `EventoAtendimento`, suas factories, relações/casts e `Agendamento::atendimento()`. Campos narrativos/motivos e hash de evento são omitidos na serialização padrão; atribuição em massa segue bloqueada. Não foram implementados Gates, operações transacionais ou telas de relatório. Detalhes em [Modelagem](MODELAGEM.md#persistência-proposta-para-atendimento-e-relatório--28102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaSchemaTest.php tests/Feature/AgendaSchemaTest.php tests/Feature/AdministrativeSchemaTest.php tests/Feature/InternalAccountsSchemaTest.php --stop-on-error`, MariaDB exclusivo | **67 testes, 115 assertions aprovados**. Resolve a pendência de conexão/validação registrada abaixo. |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaModelsTest.php tests/Feature/AgendaModelsAuthorizationTest.php`, SQLite `:memory:` | **22 testes, 130 assertions aprovados**. |
| Mesmas classes de models com `--stop-on-error`, MariaDB exclusivo | **22 testes, 130 assertions aprovados**. |
| `php vendor/bin/pint --dirty --format agent` e `git diff --check` | Aprovados; avisos de normalização CRLF/LF não são falhas. |

Todos os testes rodaram com `APP_ENV=testing`, conexão e banco explícitos no processo e `DB_URL` vazio. MariaDB usou somente `banco_de_leite_schema_test`; SQLite usou memória. Não foram aplicados migrations/seeders no desenvolvimento, não houve alteração de `.env`, dependências ou concessões. As factories são fixtures, não operações de negócio nem seeders de dados reais.

Cobertura nova dos models: vínculos até a identidade original, autores distintos, casts de datas/números, relações de base/correções/eventos, ausência de `updated_at` em evidências, bloqueio de atribuição em massa e omissão de narrativa na serialização com relações carregadas. Não comprova finalização, imutabilidade contra escrita explícita, permissões do relatório ou concorrência; esses fluxos ainda não existem. Próximo recorte: autorização de leitura/escrita conforme os acordos, seguida das operações transacionais e integração com a fila/agenda.

## Schema de atendimento e relatório — 28/10/2026

Após autorização explícita, preparadas cinco migrations novas: quatro tabelas para atendimento, rascunho, versões e eventos; ampliação dos enums da agenda com realização. Nenhuma migration anterior foi editada, nenhum model/Gate/endpoint foi adicionado e o banco de desenvolvimento não recebeu alterações. Detalhes e limites em [Modelagem](MODELAGEM.md#persistência-proposta-para-atendimento-e-relatório--28102026).

Verificações executadas com `APP_ENV=testing`, `DB_URL` vazio e conexão/banco explicitamente definidos no processo, sem editar `.env`:

| Comando / ambiente | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaSchemaTest.php tests/Feature/AgendaSchemaTest.php tests/Feature/AdministrativeSchemaTest.php tests/Feature/InternalAccountsSchemaTest.php`, SQLite `:memory:` | 62 testes e 110 assertions aprovados. Execução anterior à inclusão dos cinco casos adicionais de enum inválido. |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaSchemaTest.php tests/Feature/AgendaOperationsTest.php tests/Feature/AgendaWebTest.php`, SQLite `:memory:` | 60 testes e 240 assertions aprovados, incluindo a versão final dos 29 cenários de schema do relatório e regressão das operações/telas existentes da agenda. |
| Quatro classes de schema no MariaDB `banco_de_leite_schema_test` | Execução interrompida diante da indisponibilidade de conexão. Sem resultado válido. |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaSchemaTest.php --stop-on-error`, MariaDB exclusivo, fora do sandbox | Falhou no primeiro teste antes de preparar o schema: conexão recusada em `127.0.0.1:3306`, SQLSTATE 2002; zero assertions. Validação MariaDB pendente. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado. |

Os testes verificam unicidade de atendimento/relatório/operação/numeração, referências inexistentes, proteção do agendamento e da versão-base, texto UTF-8 de 30.000 caracteres, rejeição de estados não previstos, rollback/reaplicação preservando dados anteriores e rollback de transação de teste por operação duplicada. Testes antigos de reversão foram adaptados à ordem das novas dependências. O downgrade da agenda recusa registros de realização; o rollback das tabelas novas elimina conteúdo e não é restauração de dados.

Limites: o teste transacional não implementa nem valida a futura operação de realização. Não há validação de permissões, imutabilidade na aplicação, concorrência de relatórios, finalização/correção ou interface neste recorte. A guarda exige banco descartável; não houve aplicação de migrations/seeders no desenvolvimento, alteração de dependências ou commit. Próximo trabalho: repetir a validação MariaDB quando o servidor estiver disponível; depois implementar models e permissões no recorte autorizado seguinte.

## Especificação de atendimento e relatório — 28/10/2026

Detalhados contratos, proposta de persistência, transições, integração à fila/agenda e cenários de teste. Conferidos no código: `Agendamento` ainda não registra realização; a fila usa ausência de reserva; `Permissao` aceita somente três códigos administrativos e concessões apenas a funcionários; `User` dá acesso administrativo automático a admin. Essas regras não foram ampliadas silenciosamente para relatórios.

Questões R1–R3 respondidas pelo usuário em 28/10/2026: leitura separada da escrita/finalização, acesso automático do admin e continuidade/correção por qualquer profissional autorizado. Matriz de acesso atualizada em Contratos, distinguindo acordos de detalhes técnicos propostos. Origem dos acordos e ausência de PDF identificadas. Alterações somente em Markdown; sem testes de aplicação, migrations, seeders ou mudanças no banco nesta especificação. As verificações anteriores da agenda não validam o módulo proposto. Próximo recorte: preparar migrations e testes isolados após autorização de implementação.

## Ativação local da agenda — 28/10/2026

Após autorização para o próximo passo, aplicadas exclusivamente as quatro migrations da agenda com `php artisan migrate` e quatro opções `--path` explícitas, sem recriação do banco ou execução de seeders. `migrate:status --no-interaction` confirmou todas as 15 migrations aplicadas; as quatro da agenda estão no lote 5.

Consultas de contagem antes/depois em `banco_de_leite_dev` confirmaram preservação de duas contas internas e uma solicitação; interessadas, contatos e concessões permaneceram zerados. As três tabelas novas de agenda estão vazias. Nenhum dado fictício foi inserido no desenvolvimento, nenhuma permissão foi concedida e não houve alteração de `.env` ou dependências.

Reexecução em `banco_de_leite_schema_test`, com `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE` explícito e `DB_URL` vazio: `php vendor/bin/phpunit tests/Feature/AgendaWebTest.php tests/Feature/AgendaConcurrencyTest.php`, **11 testes e 109 assertions aprovados**. Cobre fluxo web e os dois cenários concorrentes existentes, não carga nem todas as combinações de operações. Sem mudança de código, a suíte completa não foi repetida.

Inventário da ferramenta de UI novamente retornou zero navegadores; revisão visual/responsividade e teste manual local continuam pendentes. Admin já possui acesso pelo Gate; funcionário depende da concessão explícita. Próximo recorte: detalhar atendimento/relatório e permissões profissionais antes de implementar, preservando rascunho e versões após finalização já acordados. Não houve commit nesta etapa.

## Aplicação das contas internas — 28/10/2026

Após o usuário reativar o servidor e autorizar nova tentativa, a migration `2026_10_27_202742_restrict_users_to_internal_accounts` foi aplicada isoladamente em `banco_de_leite_dev`, lote 4, usando `migrate --path=database/migrations/2026_10_27_202742_restrict_users_to_internal_accounts.php --no-interaction`. A pendência de conexão registrada em 27/10 foi resolvida.

Executado também `db:seed --class=PermissaoSeeder --no-interaction`, conforme a atualização de catálogo já prevista. Consultas posteriores confirmaram uma conta admin, uma funcionário e nenhuma doadora; enum `admin|funcionario`, obrigatório e sem padrão; três códigos no catálogo e zero concessões a funcionários. Nenhuma conta interna foi recriada e não houve `migrate:fresh` ou `DatabaseSeeder`.

`migrate:status` confirmou a aplicação e as quatro migrations da agenda ainda pendentes. `db:table users` não concluiu por falta da extensão PHP `intl`; a conferência de contas/coluna/catálogo foi feita por consultas somente de leitura, sem exibir dados pessoais. Sem alteração de código ou repetição da suíte de testes nesta aplicação. As evidências anteriores de testes continuam registradas abaixo. Não houve commit nesta etapa.

Levantamento inicial de 22/10/2026, acrescido da preparação do schema administrativo em 24/10/2026. **Implementado** indica presença no código; **validado** exige evidência de execução identificada. Este levantamento não homologou o sistema.

## Fluxo web da agenda — 27/10/2026

Implementação autorizada da próxima etapa: sete rotas em `interno/agenda`, `AgendaController`, lista paginada com filtros, admitidas sem reserva, detalhe operacional, formulários de contato/confirmação/reagendamento/cancelamento/não realização e histórico. Menu e link no acompanhamento respeitam permissões. Não inclui relatório, anexos, decisões clínicas nem mensagens automáticas.

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit` com SQLite `:memory:` | **208 testes: 206 aprovados, 2 ignorados, 951 assertions**. Os dois ignorados são os testes de concorrência que exigem MariaDB. |
| `php vendor/bin/phpunit tests/Feature/AgendaWebTest.php` no MariaDB local exclusivo `banco_de_leite_schema_test` | **9 testes, 93 assertions aprovados**. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado. |
| `php artisan route:list --path=interno/agenda --no-interaction` | Sete rotas registradas. |
| `php artisan migrate:status --no-interaction` no desenvolvimento | Dez migrations aplicadas; quatro migrations da agenda ainda pendentes. Consulta somente, sem aplicar migrations/seeders. |

Testes executados com `APP_ENV=testing`, conexão/banco explícitos e `DB_URL` vazio, protegidos pela guarda de isolamento. A primeira regressão identificou uma expectativa antiga do texto “pendente de agendamento”; ela foi atualizada para a orientação de consultar a programação, mantendo as demais verificações de admissão e ausência de aprovação clínica. A verificação de erros após redirecionamento preserva explicitamente o cookie de sessão entre as requisições do teste, conforme a sessão JSON configurada. Reexecução completa aprovada conforme tabela.

Revisão visual e responsividade **não executadas**: inventário da ferramenta retornou zero navegadores. As respostas HTML foram verificadas pelos testes HTTP, incluindo escape e mensagens de erro, sem substituir revisão visual. Nenhuma alteração de `.env`, dependências, schema ou dados de desenvolvimento. Sem commit nesta etapa. Cobertura e limites em [E-11](../SEGURANCA.md#e-11--integração-web-da-agenda-27102026).

Próximo passo: ativação autorizada das quatro migrations e do catálogo no desenvolvimento, seguida de teste manual da agenda. Depois, detalhar atendimento/relatório e permissões profissionais antes de implementar esse recorte.

## Implementado

Atualização de acesso em 27/10/2026: autocadastro e conta de doadora retirados do código ativo. Login/reset e sessões exigem conta interna; formulário de funcionário exige admin ou `criar-funcionarios`, com autoria e sem permissões automáticas. A migration de exclusão das contas antigas foi testada, mas **não aplicada no desenvolvimento: conexão MySQL recusada** nas duas tentativas. Assim, a conta antiga permanece fisicamente no banco até a aplicação, embora seu acesso esteja bloqueado pelo código.

Atualização mais recente: contatos após admissão e operações transacionais da agenda integrados a rotas/telas Blade. Veja [fluxo web](#fluxo-web-da-agenda--27102026). Ativação local do schema/catálogo e revisão visual permanecem pendentes.

| Área | O que existe | Fonte |
| --- | --- | --- |
| Sessão | Login, logout, regeneração de sessão e limitação por e-mail/IP após cinco falhas de login. | [LoginRequest](../../app/Http/Requests/Auth/LoginRequest.php), [controller](../../app/Http/Controllers/Auth/AuthenticatedSessionController.php). |
| Contas internas | Autocadastro removido. Admin ou funcionário com permissão cria funcionário com nome/e-mail, autoria e senha aleatória; a pessoa define a senha via recuperação por e-mail. Schema atualizado elimina papel doadora e padrão implícito. Aplicação local pendente. | [controller](../../app/Http/Controllers/FuncionarioController.php), [migration](../../database/migrations/2026_10_27_202742_restrict_users_to_internal_accounts.php). |
| Perfil | Consulta, edição de nome/e-mail, troca de senha e exclusão com senha atual; conta com vínculos é preservada, com erro no formulário e sessão mantida. | [ProfileController](../../app/Http/Controllers/ProfileController.php), [ProfileUpdateRequest](../../app/Http/Requests/ProfileUpdateRequest.php), [PasswordController](../../app/Http/Controllers/Auth/PasswordController.php). |
| Recuperação de senha | Solicitação e redefinição por token usando o broker Laravel. | [rotas](../../routes/auth.php), [controllers](../../app/Http/Controllers/Auth). |
| E-mail | Rotas e lógica de verificação presentes; obrigatoriedade não ativada no model. | [User](../../app/Models/User.php), [rotas](../../routes/auth.php). |
| Acesso interno | Admin e funcionário, Gates por capacidade; sessões sem conta interna válida são encerradas. | [middleware](../../app/Http/Middleware/EnsureInternalAccount.php), [rotas web](../../routes/web.php). |
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

**Atualização da base de agenda:** models/factories, autorização `gerenciar-agenda`, operações transacionais e endpoints/telas já foram implementados; evidências nas seções abaixo. As referências históricas a esses itens como pendentes foram superadas. Revisão visual e aplicação do schema/catálogo ao banco de desenvolvimento permanecem pendentes.

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

## Verificações de models e autorização da agenda — 26/10/2026

Após autorização, implementados `Agendamento`, `ReservaAgenda` e `EventoAgendamento`, factories, relações com solicitações/contatos e Gate `gerenciar-agenda`. O seeder de catálogo e a concessão/revogação auditada agora contemplam atendimento e agenda. Não foram criadas rotas, telas, operações de reserva ou permissões clínicas. Fonte do comportamento em [Contratos](CONTRATOS.md#contrato-técnico-proposto-da-agenda--26102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/AgendaModelsAuthorizationTest.php tests/Feature/EmployeeAuthorizationTest.php` em SQLite `:memory:` | **35 testes, 148 assertions aprovados**. |
| Mesmo comando em MariaDB local exclusivo `banco_de_leite_schema_test` | **35 testes, 148 assertions aprovados**. |
| `php vendor/bin/phpunit` em SQLite `:memory:` | **175 testes, 757 assertions aprovados**. |
| `php vendor/bin/pint --dirty --format agent` e `git diff --check` | Aprovados. |

Processos com `APP_ENV=testing`, conexão/banco explicitamente definidos e `DB_URL` vazio; classes protegidas pela guarda de banco isolado. A nova classe usa `RefreshDatabase`. Testes cobrem relações/casts, proteção de atribuição em massa, reserva sem ID incremental, histórico preservado após liberar reserva, autorização independente/acumulada, catálogo idempotente, revogação e autoria. O endpoint usado para testar middleware foi registrado somente dentro do teste.

Nenhuma migration/seeder ou concessão executada no desenvolvimento; nenhuma alteração de `.env`, dependências ou dados reais. Sem teste de navegador, concorrência entre conexões ou operação real de agenda. Próximo recorte: contatos após admissão e confirmação/reagendamento/cancelamento transacionais, seguidos de telas, com os limites operacionais registrados em Contratos. Evidência de segurança: [E-09](../SEGURANCA.md#e-09--models-e-autorização-da-agenda-26102026).

## Verificações das operações de agenda — 26/10/2026

Após autorização para avançar, implementados contatos após admissão e confirmação, reagendamento, cancelamento e registro de não realização. O usuário confirmou o bloqueio de sobreposição do funcionário; horários distintos no mesmo dia continuam permitidos. Regras efetivas em [Contratos](CONTRATOS.md#operações-de-backend-implementadas-em-26102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit` em SQLite `:memory:` | **199 testes: 197 aprovados, 2 ignorados, 858 assertions**. Os dois ignorados exigem MariaDB para concorrência real. |
| `php vendor/bin/phpunit tests/Feature/AgendaOperationsTest.php tests/Feature/AgendaConcurrencyTest.php` no MariaDB exclusivo `banco_de_leite_schema_test` | **24 testes e 117 assertions aprovados**. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado. |

Processos com `APP_ENV=testing`, conexão/banco explicitamente definidos e `DB_URL` vazio, sem alteração do `.env`. A guarda de isolamento precede a preparação do banco. Os testes concorrentes usam dois processos PHP e uma barreira transacional; uma primeira tentativa encontrou timeout na sincronização do teste, corrigido com sinalização por arquivos temporários removidos ao finalizar. A reexecução dos dois casos passou, assim como a execução conjunta acima.

Não foram aplicados migrations/seeders ao desenvolvimento nem alteradas dependências ou dados reais. Não há envio automático de WhatsApp. Limites e cobertura em [E-10](../SEGURANCA.md#e-10--operações-e-concorrência-da-agenda-26102026). Próximo recorte: endpoints e formulários Blade de acompanhamento/agenda, com autorização, erros de validação/conflito e apresentação do histórico. Relatório e etapas clínicas continuam fora desta entrega.
