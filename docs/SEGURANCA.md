# Acompanhamento de segurança

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
| SEG-02 / P1 | **Implementado parcialmente:** Gates de atendimento e gestão em [AppServiceProvider](../app/Providers/AppServiceProvider.php), com consulta atual de papel/concessão; sem liberação clínica global. | Matriz de admin/funcionário/doadora/visitante, concessão/revogação e autorizações indevidas cobertas. Próximo: aplicar Gates às rotas reais de pedidos e conferir escopo de cada ação. Rotas antigas de apoio continuam por papel. | E-03 e E-04: Gates e middleware em rota exclusiva de teste, em SQLite e MariaDB; não valida futuros endpoints. |
| SEG-03 / P1 | **Implementado nos fluxos inspecionados:** Eloquent/recursos do framework, sem SQL manual concatenando entrada identificado em `app` e `routes`. Fontes: controllers de [autenticação](../app/Http/Controllers/Auth), [perfil](../app/Http/Controllers/ProfileController.php). | Revisar consultas novas, principalmente buscas de correspondências. Valores devem usar parâmetros; colunas e ordenação devem ter lista permitida. Testar entrada com sintaxe SQL: não autentica indevidamente, não amplia resultados e não modifica registros alheios. Uma rejeição pelo validador não comprova proteção da consulta subjacente. | Inspeção estática em 24/10/2026; testes específicos pendentes. |
| SEG-04 / P1 | **Implementado nas views inspecionadas:** escape `{{ }}`; sem `{!! !!}` encontrado em [views](../resources/views). | Testar nome, observações e relatórios nos pontos de exibição que existirem: conteúdo malicioso aparece como texto, sem criar HTML executável. Revisar separadamente contextos HTML, JavaScript e URLs; testar no navegador quando houver interação dinâmica. | Inspeção estática em 24/10/2026; testes específicos pendentes. Relatórios ainda não existem. |
| SEG-05 / P1 | **Implementado:** formulários com `@csrf`; sem exclusão identificada na [configuração web](../bootstrap/app.php). | Com proteção realmente ativa, requisição de alteração sem token/inválida deve ser rejeitada sem alterar dados; requisição legítima deve funcionar. O ambiente usual de testes Laravel desativa a verificação CSRF: teste HTTP comum aprovado não prova esse controle. Registrar como a proteção foi exercitada. | Inspeção estática em 24/10/2026; teste específico com middleware ativo pendente. |
| SEG-06 / P1 | **Implementado parcialmente:** [login](../app/Http/Requests/Auth/LoginRequest.php) limita cinco falhas por e-mail/IP; [rotas](../routes/auth.php) limitam verificação de e-mail. [Broker](../config/auth.php) tem intervalo para recuperação. Cadastro não tem throttle explícito na rota. | Testar bloqueio e liberação após a janela, sem depender de espera real. Definir limites para cadastro, recuperação e futuras solicitações; verificar que a rejeição não cria registros nem dispara mensagens adicionais. Revisar respostas de recuperação para reduzir descoberta de contas, sem afirmar que o comportamento atual é neutro. | Implementação inspecionada; testes específicos de abuso pendentes. |
| SEG-07 / P2 | **Implementado:** hash de senha, senha atual para troca/exclusão e regeneração/invalidação de sessão. Fontes: [controllers](../app/Http/Controllers/Auth), [perfil](../app/Http/Controllers/ProfileController.php). | Complementar testes de token de recuperação inválido, expirado e reutilizado; sessão anterior após logout; política de senhas para funcionários a definir. Conferir cookies e transporte no ambiente publicado (SEG-13). | Testes básicos existentes em [Auth](../tests/Feature/Auth) e [ProfileTest](../tests/Feature/ProfileTest.php), executados na suíte anterior; não cobrem toda esta linha. |
| SEG-08 / P2 | **A definir:** [User](../app/Models/User.php) não implementa `MustVerifyEmail`; rota com `verified` não impõe a confirmação nesse estado. | Decidir confirmação de e-mail e provisionamento das contas internas; revisar se o cadastro público de contas permanece necessário no fluxo sem conta de doadora. Depois testar exatamente a política acordada. | Inspeção estática; decisão e testes pendentes. |
| SEG-09 / P1 | **Implementado:** FKs preservam autoria; [ProfileController](../app/Http/Controllers/ProfileController.php) converte restrição de exclusão em mensagem e mantém a sessão. | Testados histórico de contato, concessão revogada e conta sem vínculos; exclusão permitida não recria a conta ao encerrar a sessão. Desativação de funcionários continua pendente. | E-01: schema. E-03 e E-04: fluxo web em SQLite e MariaDB, incluindo restrição de exclusão. |

## Segurança dos módulos e da operação

| ID / prioridade | Estado e escopo | Critério de verificação / próximo trabalho | Evidência |
| --- | --- | --- | --- |
| SEG-10 / P1 | **Proposto:** integridade das resoluções, flags e admissão conforme [Contratos](backend/CONTRATOS.md). Schema preparado; ações ainda não implementadas. | Impedir decisão sem contato exigido, alteração de estado/autor pelo cliente e execução parcial. Testar duas operações concorrentes, versões desatualizadas e repetição da mesma operação: sem dupla admissão nem perda de histórico. Autor deve vir da sessão. | E-01 cobre apenas restrições de banco; testes de aplicação e concorrência pendentes. |
| SEG-11 / P1 | **Proposto:** proteção dos dados pessoais no pedido e na consulta de correspondências. | Resposta pública não revela se CPF/telefone já existe nem dados de outros pedidos. Testar tentativa de obter detalhe por ID/protocolo e acesso às flags sem permissão. Não transformar CPF/telefone/protocolo em credencial. Definir campos visíveis por função. | Pendente da implementação; [Contratos](backend/CONTRATOS.md). |
| SEG-12 / P2 | **Proposto:** relatórios e anexos clínicos restritos, sem upload público nesta etapa. | Quando implementados: armazenamento privado; acesso/download autorizado por registro; validar tamanho e conteúdo; nomes gerados; rejeitar formatos executáveis e caminhos manipulados. Testar upload e download negados, substituições e preservação da evidência anterior. | Fora do recorte atual; [Modelagem](backend/MODELAGEM.md). |
| SEG-13 / P2 | **Não verificado:** configuração do ambiente de publicação. [Sessão](../config/session.php) permite configurar cookies seguros; não comprova valor efetivo no servidor. | Antes de publicar: verificar HTTPS, debug desligado, cookies Secure/HttpOnly/SameSite, diretório público correto e ausência de exposição de `.env`, logs e backups. Conferir privilégios mínimos do banco e cabeçalhos adequados à interface. Registrar ambiente sem copiar segredos. | Pendente; não foi inspecionado ambiente de produção. |
| SEG-14 / P2 | **Não verificado:** vulnerabilidades conhecidas e atualização de dependências. | Auditar Composer e npm, registrar data/versões, resultado e aplicabilidade dos avisos. Após correção autorizada, repetir auditoria e testes afetados. Não executar atualizações automáticas indiscriminadas. | Auditorias não executadas nesta revisão; não há declaração de ausência de vulnerabilidades. |
| SEG-15 / P2 | **A definir:** proteção operacional dos dados, retenção, backups, logs e resposta a incidentes. | Definir acesso e retenção com a equipe responsável; evitar senhas/tokens/documentos em logs. Testar restauração em ambiente isolado e registrar quem pode consultar/exportar dados. Definir procedimento para incidente e revisão de acessos. | Pendente. Este documento não atesta conformidade legal. |
| SEG-16 / P1 | **Implementado:** guarda própria no teste de schema e helper em [TestCase](../tests/TestCase.php), chamado por `AdministrativeModelsTest`, `EmployeeAuthorizationTest` e `ProfileTest`. | Aceitam somente SQLite em memória ou `banco_de_leite_schema_test` local, em testing, sem URL/configuração em cache. Não presumir que classes antigas chamem o helper; conferir a seleção antes de execução destrutiva. | E-01, E-03 e E-04; sem teste negativo dedicado da guarda. |

## Evidências registradas

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
