# Acompanhamento de segurança

## E-06 — Ativação local (24/10/2026)

- Com autorização do usuário, seis migrations aditivas e somente `PermissaoSeeder` foram executados em `banco_de_leite_dev`. Status final: dez migrations aplicadas, três contas, um item de catálogo, zero concessões e zero solicitações. Sem recriação do banco, alteração de credenciais ou concessão automática de acesso.
- Checks HTTP em servidor temporário usando exclusivamente o banco de testes: formulário e confirmação responderam 200; fila interna sem autenticação respondeu 302. Nenhum pedido foi inserido nessa verificação.
- Limites: navegador indisponível na ferramenta (inventário vazio); não houve revisão visual, login interativo ou teste de responsividade. Os testes automatizados anteriores não foram repetidos nesta ativação. Detalhes em [Status](backend/STATUS.md#ativação-local--24102026).

Documento principal para localizar controles, reforços e testes de segurança. Criado em **24/10/2026**, a pedido do usuário, a partir da revisão estática dos fluxos existentes e das evidências de banco registradas em [Status](backend/STATUS.md). Não representa auditoria completa, certificação ou homologação para dados reais.

## Como trabalhar neste documento

1. Leia o [Guia](GUIA.md) e escolha os IDs relacionados ao escopo da tarefa. Confira as fontes indicadas e o código atual antes de corrigir; o checklist não substitui essa leitura localizada.
2. Registre o problema concreto ou a proteção a verificar. Diferencie vulnerabilidade demonstrada, oportunidade de reforço, teste ausente e funcionalidade ainda não implementada.
3. Implemente somente o escopo autorizado. Um item pendente não autoriza automaticamente alterações de dependências, infraestrutura, banco ou publicação. Siga [AGENTS.md](../AGENTS.md) e as skills aplicáveis.
4. Teste o comportamento permitido e a tentativa indevida, conferindo também ausência de alteração/vazamento de dados quando o acesso for negado. Use dados fictícios e ambiente isolado.
5. Atualize o item e acrescente uma ficha de evidência no mesmo PR. Registre limitações e o próximo trabalho; não marque como validado só porque existe código ou a suíte geral passou.

Este registro permite revisão por assunto sem repetir uma análise integral a cada tarefa. Mudanças de autenticação, infraestrutura ou dependências podem exigir uma revisão mais ampla; resultados antigos não garantem o comportamento atual.

## Estados e prioridade

Os estados **proposto, acordado, implementado e validado** seguem o Guia. Nas tabelas, implementação e verificação são separadas: um controle implementado pode continuar com teste específico pendente. “Parcial” qualifica somente a parte indicada; “a definir” não equivale a aprovação.

Prioridades abaixo são **propostas técnicas**, ainda sem atribuição de responsáveis:

- **P1:** tratar junto ao próximo trabalho de autorização ou antes de expor o fluxo afetado.
- **P2:** tratar na implementação do módulo correspondente ou antes da publicação/dados reais, conforme indicado.
- **P3:** melhoria posterior, desde que não bloqueie requisito do fluxo. Nenhum item inicial foi classificado aqui.

## Controles existentes e verificações pendentes

| ID / prioridade | Estado observado e fonte | Reforço ou teste esperado | Verificação atual |
| --- | --- | --- | --- |
| SEG-01 / P1 | **Implementado:** `role` removido do `Fillable` de [User](../app/Models/User.php); cadastro e perfil mantêm listas explícitas de campos. | Manter papel controlado por operação interna autorizada. Testes enviam `role=admin` no cadastro/perfil e tentam preenchimento em massa: sem promoção. Gestão de contas/papéis ainda sem interface. | Validado no recorte em SQLite e MariaDB: E-03 e E-04. |
| SEG-02 / P1 | **Implementado no recorte:** Gates nas rotas de recebimento, fila, detalhe, análise, contatos, decisões e acompanhamento. Consulta de IDs após autorização. | Testes de visitante, doadora, funcionário sem concessão, funcionário autorizado, admin e revogação. Revisar escopo quando existirem outras unidades e antes das operações clínicas. | E-03/E-04, E-05 e E-07; não concede permissão clínica. |
| SEG-03 / P1 | **Implementado no recorte:** consultas com parâmetros; nomes de campos fixos e ordenação selecionada entre opções permitidas. Fontes: [controller](../app/Http/Controllers/SolicitacaoController.php) e [model](../app/Models/Solicitacao.php). | Entrada com sintaxe SQL permanece dado; filtro de ordenação arbitrário é rejeitado. Revisar consultas novas e desempenho das correspondências. | E-05: persistência e filtros exercitados; não é auditoria completa de injeção SQL. |
| SEG-04 / P1 | **Implementado no recorte:** views de [solicitações](../resources/views/solicitacoes) exibem dados com escape Blade. | Teste grava texto com script e confirma saída escapada no detalhe. Relatórios clínicos/JavaScript dinâmico e validação em navegador continuam pendentes. | E-05: resposta HTML testada; sem execução visual em navegador. |
| SEG-05 / P1 | **Implementado:** formulários com `@csrf` e middleware web padrão. | Teste do formulário público substitui apenas o bypass de ambiente de teste do `PreventRequestForgery`: sem token retorna 419 e não grava; token da sessão permite gravar. Repetir para novos fluxos relevantes. | E-05: proteção de token ativa no teste; origem HTTP/navegador real não foi homologada. |
| SEG-06 / P1 | **Implementado parcialmente:** login e verificação mantêm limites existentes. Recebimento usa 5 POSTs/minuto por IP no público e 30 por usuário no interno. | Teste ultrapassa limite público sem nova gravação. Avaliar limiares em redes compartilhadas e cobertura do limite interno. Cadastro de contas/recuperação de senha ainda exigem os reforços já apontados. | E-05 para recebimento público; não valida proteção de infraestrutura ou abuso distribuído. |
| SEG-07 / P2 | **Implementado:** hash de senha, senha atual para troca/exclusão e regeneração/invalidação de sessão. Fontes: [controllers](../app/Http/Controllers/Auth), [perfil](../app/Http/Controllers/ProfileController.php). | Complementar testes de token de recuperação inválido, expirado e reutilizado; sessão anterior após logout; política de senhas para funcionários a definir. Conferir cookies e transporte no ambiente publicado (SEG-13). | Testes básicos existentes em [Auth](../tests/Feature/Auth) e [ProfileTest](../tests/Feature/ProfileTest.php), executados na suíte anterior; não cobrem toda esta linha. |
| SEG-08 / P2 | **A definir:** [User](../app/Models/User.php) não implementa `MustVerifyEmail`; rota com `verified` não impõe a confirmação nesse estado. | Decidir confirmação de e-mail e provisionamento das contas internas; revisar se o cadastro público de contas permanece necessário no fluxo sem conta de doadora. Depois testar exatamente a política acordada. | Inspeção estática; decisão e testes pendentes. |
| SEG-09 / P1 | **Implementado:** FKs preservam autoria; [ProfileController](../app/Http/Controllers/ProfileController.php) converte restrição de exclusão em mensagem e mantém a sessão. | Testados histórico de contato, concessão revogada e conta sem vínculos; exclusão permitida não recria a conta ao encerrar a sessão. Desativação de funcionários continua pendente. | E-01: schema. E-03 e E-04: fluxo web em SQLite e MariaDB, incluindo restrição de exclusão. |

## Segurança dos módulos e da operação

| ID / prioridade | Estado e escopo | Critério de verificação / próximo trabalho | Evidência |
| --- | --- | --- | --- |
| SEG-10 / P1 | **Implementado no recorte:** recebimento transacional com token de sessão; decisões transacionais com UUID, conteúdo, autoria, versões e travas nos pedidos selecionados. Contato abrangente e esclarecido exigido para concluir. | Replay, conflito de conteúdo, versões antigas, rollback por falha de auditoria, preservação do cadastro existente e correspondências pendentes cobertos. Ainda verificar concorrência real, inserções simultâneas e volume. | E-05 e E-07; testes de versões antigas não comprovam concorrência real entre conexões. |
| SEG-11 / P1 | **Implementado no recorte:** confirmação pública neutra sem identificadores; correspondências restritas ao atendimento. | Testada ausência de dados na confirmação, negativa de consulta interna e aceitação de novo pedido repetido sem exposição pública. Novas telas/exportações devem manter o escopo administrativo. | E-05; sem consulta pública por CPF/telefone/protocolo. |
| SEG-12 / P2 | **Proposto:** relatórios e anexos clínicos restritos, sem upload público nesta etapa. | Quando implementados: armazenamento privado; acesso/download autorizado por registro; validar tamanho e conteúdo; nomes gerados; rejeitar formatos executáveis e caminhos manipulados. Testar upload e download negados, substituições e preservação da evidência anterior. | Fora do recorte atual; [Modelagem](backend/MODELAGEM.md). |
| SEG-13 / P2 | **Não verificado:** configuração do ambiente de publicação. [Sessão](../config/session.php) permite configurar cookies seguros; não comprova valor efetivo no servidor. | Antes de publicar: verificar HTTPS, debug desligado, cookies Secure/HttpOnly/SameSite, diretório público correto e ausência de exposição de `.env`, logs e backups. Conferir privilégios mínimos do banco e cabeçalhos adequados à interface. Registrar ambiente sem copiar segredos. | Pendente; não foi inspecionado ambiente de produção. |
| SEG-14 / P2 | **Não verificado:** vulnerabilidades conhecidas e atualização de dependências. | Auditar Composer e npm, registrar data/versões, resultado e aplicabilidade dos avisos. Após correção autorizada, repetir auditoria e testes afetados. Não executar atualizações automáticas indiscriminadas. | Auditorias não executadas nesta revisão; não há declaração de ausência de vulnerabilidades. |
| SEG-15 / P2 | **A definir:** proteção operacional dos dados, retenção, backups, logs e resposta a incidentes. | Definir acesso e retenção com a equipe responsável; evitar senhas/tokens/documentos em logs. Testar restauração em ambiente isolado e registrar quem pode consultar/exportar dados. Definir procedimento para incidente e revisão de acessos. | Pendente. Este documento não atesta conformidade legal. |
| SEG-16 / P1 | **Implementado:** guarda do teste de schema e helper em [TestCase](../tests/TestCase.php), chamado pelos testes de models, autorização, perfil e `SolicitacaoFlowTest`. | Aceitam somente SQLite em memória ou `banco_de_leite_schema_test` local, em testing, sem URL/configuração em cache. Não presumir que classes antigas chamem o helper; conferir a seleção antes de execução destrutiva. | E-01, E-03, E-04 e E-05; sem teste negativo dedicado da guarda. |

## Evidências registradas

### E-10 — operações e concorrência da agenda, 26/10/2026

- Itens: SEG-02, SEG-09, SEG-10 e SEG-16. Fontes: `Agendamento`, `ContatoSolicitacao`, [AgendaOperationsTest](../tests/Feature/AgendaOperationsTest.php) e [AgendaConcurrencyTest](../tests/Feature/AgendaConcurrencyTest.php), alterações locais ainda sem commit.
- Implementados contatos de acompanhamento e confirmação/reagendamento/cancelamento/não realização, com Gate antes de qualquer repetição, validação explícita, autoria do servidor, versões, UUID/hash e transação incluindo auditoria. Falha na auditoria reverte as demais gravações. Admissão não se converte em aprovação clínica.
- Bloqueio de sobreposição autorizado pelo usuário: trava dos responsáveis em ordem estável, consulta de intervalos confirmados e mensagem de conflito. Horários adjacentes são permitidos; deslocamento continua manual.
- `AgendaOperationsTest`: 22 testes e 101 assertions aprovados em SQLite e MariaDB. Cobrem entradas inválidas, contato incompatível, replay/conflito, permissão revogada, conversão de fuso, sobreposição, versões, histórico e rollback.
- `AgendaConcurrencyTest`: 2 testes e 16 assertions aprovados no MariaDB exclusivo `banco_de_leite_schema_test`, com processos/conexões independentes. Disputas por mesmo responsável/horário entre pedidos distintos e pelo mesmo pedido com responsáveis distintos resultam em uma confirmação e um bloqueio. Esses testes são ignorados em SQLite antes de preparar o banco.
- Validação restrita a essas operações e dois cenários concorrentes. Não cobre todas as combinações concorrentes de reagendamento/cancelamento, carga, interface, CSRF em endpoints futuros ou regras clínicas. Revisão por outra pessoa pendente. Resultados completos em [Status](backend/STATUS.md#verificações-das-operações-de-agenda--26102026).

### E-09 — models e autorização da agenda, 26/10/2026

- Implementados `Agendamento`, `ReservaAgenda` e `EventoAgendamento`, com proteção de atribuição em massa, relações e casts. Factories produzem dados fictícios relacionados; liberar a reserva via model não exclui agenda/histórico nem concede acesso ao responsável designado.
- Gate `gerenciar-agenda` separado de atendimento. Admin tem alcance administrativo; funcionário depende do código concedido e ativo; visitante/doadora são negados, inclusive doadora com concessão indevida. Não há bypass global para relatórios ou aprovação clínica.
- Catálogo idempotente prepara dois códigos sem criar funcionários/concessões. Concessão/revogação mantém lista explícita de códigos, motivo, autor e auditoria transacional. Verificados acúmulo, revogação independente, reativação sem duplicação, papel atualizado apesar de instância antiga e bloqueio de autoconcessão.
- [AgendaModelsAuthorizationTest](../tests/Feature/AgendaModelsAuthorizationTest.php) e [EmployeeAuthorizationTest](../tests/Feature/EmployeeAuthorizationTest.php): 35 testes e 148 assertions aprovados em SQLite e MariaDB exclusivo. A rota de agenda usada para verificar middleware existe somente dentro do teste. Não representa endpoint disponibilizado na aplicação.
- Nenhuma migration/seeder no desenvolvimento, nenhum acesso concedido a contas existentes e nenhuma permissão clínica criada. Operações transacionais da agenda, concorrência real e interface continuam pendentes. Resultados da suíte completa em [Status](backend/STATUS.md#verificações-de-models-e-autorização-da-agenda--26102026).

### E-08 — schema da agenda, 26/10/2026

Fontes: [AgendaSchemaTest](../tests/Feature/AgendaSchemaTest.php), quatro migrations identificadas em [Modelagem](backend/MODELAGEM.md#desenho-físico-proposto-para-agenda--26102026) e [Status](backend/STATUS.md#verificações-do-schema-de-agenda--26102026).

- Guarda anterior à preparação destrutiva dos testes: somente SQLite `:memory:` ou MariaDB local `banco_de_leite_schema_test`, ambiente testing, sem URL externa/configuração em cache; no MariaDB confere também o nome efetivo da conexão. Não executado `migrate:fresh` no desenvolvimento.
- Verificados contatos legados com contexto `analise` e UUID/hash nulos; UUID de operação único quando informado; uma reserva atual por pedido e por agendamento; um sucessor por agendamento substituído; FKs e preservação de histórico após liberar a reserva. Rollback/reaplicação do recorte testados com dados fictícios anteriores à ampliação.
- Transação de teste com evento duplicado é revertida integralmente. Isso valida a base física, não uma operação de agendamento implementada. Datas, coerência entre pedido/contato/reserva, contexto obrigatório e idempotência de endpoints continuam responsabilidades futuras da aplicação.
- Limites: nenhum Gate ou endpoint de agenda implementado; não valida acesso clínico, concorrência entre conexões, sobreposição de horários, carga ou navegador. `down()` perde dados do novo recorte e não foi executado no banco de desenvolvimento. Contagens de testes e ambientes em Status.

### E-07 — atendimento e decisões administrativas, 26/10/2026

Fontes: [AtendimentoSolicitacaoTest](../tests/Feature/AtendimentoSolicitacaoTest.php), [controller](../app/Http/Controllers/AtendimentoSolicitacaoController.php), [resoluções](../app/Models/ResolucaoSolicitacao.php) e [contrato efetivo](backend/CONTRATOS.md#atendimento-administrativo-implementado-em-26102026).

- Cobertura de login/permissão antes de consultar IDs, funcionário autorizado e revogação, autoria controlada pelo servidor, contato abrangente, estados e versões, repetição da operação e rollback integral por falha de auditoria.
- Verificados admissão sem criar conta, escolha do principal, arquivamento sem exclusão, vínculo explícito sem sobrescrever identidade existente, histórico e correspondências revisadas. Dados alterados, novos alvos e nova dúvida pendente não herdam uma conferência anterior.
- CSRF verificado com proteção ativa no teste de decisão (sem token: 419, sem escrita; token da sessão: decisão persistida). Resumo de contato com script aparece escapado no HTML. Acompanhamentos negam acesso sem permissão e não exibem pedido ainda não admitido.
- Comandos, ambientes e contagens finais ficam em [Status](backend/STATUS.md#verificações-de-atendimento-administrativo--26102026). Não foram aplicadas migrations, seeders, alterações de `.env` ou dados ao banco de desenvolvimento nesta etapa.
- Limites: sem navegador, execução real de Alpine, revisão de acessibilidade/responsividade, envio real de mensagens, carga ou concorrência entre conexões. Os testes com versões desatualizadas verificam conflitos sequenciais. As travas não são prova de tratamento de todas as entradas simultâneas; homologação operacional continua pendente.

### E-01 — integridade do schema, 24/10/2026

- Itens: SEG-09, SEG-10 e SEG-16, somente nos limites indicados.
- Origem: execução anterior nesta sessão, detalhada em [Status](backend/STATUS.md#integração-mariadb-em-banco-exclusivo--24102026).
- Ambiente: Laravel 13.34.0, PHP 8.5.10, PHPUnit 12.5.37; MariaDB 10.4.32, banco exclusivo `banco_de_leite_schema_test`.
- Comando: `php vendor/bin/phpunit tests/Feature/AdministrativeSchemaTest.php`, com configuração isolada descrita no Status.
- Resultado: 20 testes, 34 assertions aprovados; integridade, unicidade técnica, preservação de referências e rollback/reaplicação.
- Limites: sem validação de autorização HTTP, CSRF, XSS, injeção SQL nas futuras buscas, concorrência ou infraestrutura. O banco de desenvolvimento não recebeu as novas migrations.
- Referência de código: arquivos locais ainda sem commit identificado para esta entrega. Vincular commit/PR quando existir; não presumir cobertura de alterações posteriores.

### E-02 — inspeção estática inicial, 24/10/2026

- Itens: SEG-01 a SEG-09, nas fontes indicadas nas tabelas.
- Método: leitura de controllers, requests, model, middleware, rotas, configuração web/sessão e busca por SQL manual e saída Blade sem escape.
- Resultado: controles e pendências registrados acima. Não houve teste de exploração ou varredura de dependências.
- Referência: revisão na conversa que originou este documento, sem relatório externo ou aprovação da equipe. Responsável por revisão independente: não atribuído.

### E-03 — models, permissões e exclusão com histórico, 24/10/2026

- Itens: SEG-01, SEG-02, SEG-09 e SEG-16 no recorte implementado. Autorização de models e funcionários solicitada pelo usuário nesta sessão.
- Fontes: [AdministrativeModelsTest](../tests/Feature/AdministrativeModelsTest.php), [EmployeeAuthorizationTest](../tests/Feature/EmployeeAuthorizationTest.php) e [ProfileTest](../tests/Feature/ProfileTest.php). Alterações locais, sem commit/PR criado nesta tarefa; revisão independente não atribuída.
- Ambiente validado: PHP 8.5.10, Laravel 13.34.0 e SQLite `:memory:`. Suíte completa `php vendor/bin/phpunit`: 72 testes, 224 assertions aprovados. Pint aprovado.
- Cobertura: relações/casts, campos protegidos, matriz dos Gates, perda imediata de acesso após revogação, bloqueio de autoconcessão/revogação indevida, motivos e destinatários inválidos, rollback se a auditoria falha, histórico de reconcessão e preservação de conta/sessão em exclusão negada. A rota de integração do middleware existe somente durante o teste.
- Correção durante a verificação: logout padrão após a exclusão recriava a conta ao salvar o token; substituído por encerramento sem rotação do token da conta removida. Teste existente de exclusão passou após o ajuste.
- Tentativa MariaDB: seleção das três classes no banco exclusivo retornou 32 erros de conexão recusada em `127.0.0.1:3306`, sem assertions. Processo do servidor não encontrado. Não foi aprovação nem falha funcional demonstrada; a reexecução posterior está registrada em E-04.
- Limites: não comprova concorrência real entre conexões, segurança clínica, interface de gestão ou integração das futuras rotas. Não houve migrations/seeders no banco de desenvolvimento nem alteração do `.env`.

### E-04 — reexecução dos models e autorização no MariaDB, 24/10/2026

- Itens: SEG-01, SEG-02, SEG-09 e SEG-16, dentro do mesmo recorte de E-03. Reexecução solicitada pelo usuário após ativar o servidor.
- Comando: `php vendor/bin/phpunit tests/Feature/AdministrativeModelsTest.php tests/Feature/EmployeeAuthorizationTest.php tests/Feature/ProfileTest.php`.
- Ambiente: MariaDB 10.4.32, banco exclusivo `banco_de_leite_schema_test`; variáveis somente no processo: `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test`, `DB_URL` vazio. Sem configuração em cache; guardas de isolamento conferidas antes da execução.
- Resultado: **32 testes e 150 assertions aprovados**. Nenhuma alteração de código foi necessária nesta reexecução. Fontes de teste e limitações funcionais seguem E-03; não houve revisão independente nem commit nesta tarefa.
- Estado final do banco de testes: 20 tabelas, 10 migrations registradas e zero registros de aplicação. Estas classes usam `RefreshDatabase`: os dados fictícios foram revertidos e a estrutura foi mantida, diferentemente do teardown do teste de schema em E-01.
- Preservação do desenvolvimento: consulta somente de leitura confirmou quatro migrations antigas aplicadas e seis novas pendentes. Nenhuma alteração de `.env` ou aplicação de migrations no `banco_de_leite_dev`.
- Limites: seleção de três classes, não a suíte inteira em MariaDB; não comprova concorrência real entre conexões, MySQL 8, interface final ou futuros endpoints. A validação completa em SQLite de E-03 permanece como evidência anterior.

### E-05 — recebimento, fila e correspondências, 24/10/2026

- Escopo autorizado: formulário público/interno, fila e detalhe administrativo. Fonte: [SolicitacaoFlowTest](../tests/Feature/SolicitacaoFlowTest.php), controller, request, model e views indicados nas linhas acima.
- Verificação final: `php vendor/bin/phpunit tests/Feature/SolicitacaoFlowTest.php` no MariaDB 10.4.32 exclusivo: **36 testes, 233 assertions aprovados**. `php vendor/bin/phpunit` em SQLite `:memory:`: **108 testes, 457 assertions aprovados**. Pint aprovado. Ambos usaram `APP_ENV=testing`, `DB_URL` vazio e bancos isolados; nunca o banco de desenvolvimento.
- Cenários: campos obrigatórios e limites, CPF, fixo/celular/ambos, WhatsApp, normalização, neutralidade pública, autoria interna, campos privilegiados ignorados, replay/conflito, token de outra sessão/contexto/expirado, limitação pública, acesso negado antes de buscar IDs, revogação, CSRF ativo, texto escapado, rollback de recebimento/evento, paginação e correspondências atuais (arquivados excluídos, telefones cruzados, e-mail sem distinção de caixa, cadastro admitido sem contar duas vezes a origem).
- Referência de código: base iniciada no commit `ffb2c0f`, acrescida dos testes e ajustes locais desta tarefa; não foi criado novo commit pelo agente. Revisão independente não atribuída.
- Limites: não valida navegador real, responsividade/acessibilidade visual, carga, concorrência real ou infraestrutura. Os limites de envio precisam de revisão operacional. Testes de conferência, contato, arquivamento e admissão ainda dependem da implementação. Não foram alterados `.env` nem aplicadas migrations/seeders ao desenvolvimento.

## Ficha para atualizar um item

Copie este bloco para registrar uma execução, mantendo as evidências anteriores:

```text
ID(s): SEG-...
Data:
Responsável pela execução: (preencher somente quando identificado)
Tipo: falha reproduzida / reforço / teste ausente / requisito futuro
Escopo e comportamento esperado:
Fonte e commit/PR: (ou arquivos e indicação de alterações locais)
Estado de implementação:
Mudança realizada:
Teste/cenário e comando ou procedimento:
Ambiente e banco isolado:
Resultado observado: aprovado / falhou / não executado (motivo)
Evidência: arquivo de teste, saída resumida ou relatório sem dados sensíveis
Limitações e risco ainda existente:
Estado de validação: pendente / parcial / validado no escopo
Próximo trabalho:
Revisão por outra pessoa: pendente ou identificação real
```

## Quando revisar novamente

| Mudança | Itens mínimos a consultar |
| --- | --- |
| Model, cadastro, perfil ou permissões | SEG-01, SEG-02, SEG-08, SEG-09 |
| Consulta, filtro, busca ou ordenação | SEG-02, SEG-03, SEG-11 |
| Formulário, nova rota de escrita ou view com dados do usuário | SEG-02, SEG-04, SEG-05, SEG-06 |
| Autenticação, sessão ou recuperação | SEG-01, SEG-06, SEG-07, SEG-08, SEG-13 |
| Resolução, histórico ou alteração de schema | SEG-09, SEG-10, SEG-16 |
| Relatório, anexo, exportação ou log | SEG-02, SEG-04, SEG-11, SEG-12, SEG-15 |
| Dependências, servidor ou publicação | SEG-13, SEG-14, SEG-15 |

Se uma mudança afetar a premissa de uma evidência, marque a validação correspondente como pendente de reexecução e mantenha o resultado anterior como histórico. Cadência periódica e responsáveis pelo acompanhamento ainda dependem de definição da equipe.
