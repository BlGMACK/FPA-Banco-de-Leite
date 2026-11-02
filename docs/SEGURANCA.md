# Acompanhamento de segurança

Documento principal para localizar controles, reforços e testes de segurança. Criado em **24/10/2026**, a pedido do usuário, a partir da revisão estática dos fluxos existentes e das evidências de banco registradas em [Status](backend/STATUS.md). Não representa auditoria completa, certificação ou homologação para dados reais.

Situação em 29/10/2026: os controles de acesso, CSRF, escape, transação e auditoria estão implementados e testados nos módulos de recebimento, atendimento, agenda e relatórios (evidências E-01 a E-19). Continuam sem verificação: comportamento em navegador real, ambiente de publicação (SEG-13), auditoria de dependências (SEG-14) e proteção operacional dos dados (SEG-15). Anexos de exames (SEG-12) foram implementados em 01/11/2026 (E-22); retenção e backup continuam pendentes.

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
| SEG-01 / P1 | **Implementado:** `role` removido do `Fillable` de [User](../app/Models/User.php); cadastro e perfil mantêm listas explícitas de campos. | Manter papel controlado por operação interna autorizada. Testes enviam `role=admin` no cadastro/perfil e tentam preenchimento em massa: sem promoção. Alteração de papel e desativação de contas continuam sem interface; concessão de permissões tem tela somente para admin (E-20). | Validado no recorte em SQLite e MariaDB: E-03 e E-04. |
| SEG-02 / P1 | **Implementado:** Gates nas rotas de recebimento, fila, detalhe, análise, contatos, decisões, acompanhamento, agenda, relatórios e criação de funcionários. Consulta de IDs após autorização. Contas de doadora não existem mais. | Testes de visitante, funcionário sem concessão, funcionário autorizado, admin e revogação em cada módulo. Revisar escopo quando existirem outras unidades e antes das operações clínicas. | E-03/E-04, E-05, E-07, E-09, E-11, E-12, E-17 e E-19; não concede permissão clínica. |
| SEG-03 / P1 | **Implementado no recorte:** consultas com parâmetros; nomes de campos fixos e ordenação selecionada entre opções permitidas. Fontes: [controller](../app/Http/Controllers/SolicitacaoController.php) e [model](../app/Models/Solicitacao.php). | Entrada com sintaxe SQL permanece dado; filtro de ordenação arbitrário é rejeitado. Revisar consultas novas e desempenho das correspondências. | E-05: persistência e filtros exercitados; não é auditoria completa de injeção SQL. |
| SEG-04 / P1 | **Implementado:** views de [solicitações](../resources/views/solicitacoes), [agenda](../resources/views/agenda) e [relatórios](../resources/views/relatorios) exibem dados com escape Blade; narrativas preservam quebras de linha sem aceitar HTML. | Testes gravam texto com script e confirmam saída escapada. Validação em navegador e JavaScript dinâmico continuam pendentes. | E-05, E-11 e E-19: respostas HTML testadas; sem execução visual em navegador. |
| SEG-05 / P1 | **Implementado:** formulários com `@csrf` e middleware web padrão. | Teste do formulário público substitui apenas o bypass de ambiente de teste do `PreventRequestForgery`: sem token retorna 419 e não grava; token da sessão permite gravar. Repetir para novos fluxos relevantes. | E-05: proteção de token ativa no teste; origem HTTP/navegador real não foi homologada. |
| SEG-06 / P1 | **Implementado parcialmente:** login e verificação mantêm limites existentes. Recebimento usa 5 POSTs/minuto por IP no público e 30 por usuário no interno. | Teste ultrapassa limite público sem nova gravação. Avaliar limiares em redes compartilhadas e cobertura do limite interno. Cadastro de contas/recuperação de senha ainda exigem os reforços já apontados. | E-05 para recebimento público; não valida proteção de infraestrutura ou abuso distribuído. |
| SEG-07 / P2 | **Implementado:** hash de senha, senha atual para troca/exclusão e regeneração/invalidação de sessão. Fontes: [controllers](../app/Http/Controllers/Auth), [perfil](../app/Http/Controllers/ProfileController.php). | Complementar testes de token de recuperação inválido, expirado e reutilizado; sessão anterior após logout; política de senhas para funcionários a definir. Conferir cookies e transporte no ambiente publicado (SEG-13). | Testes básicos existentes em [Auth](../tests/Feature/Auth) e [ProfileTest](../tests/Feature/ProfileTest.php), executados na suíte anterior; não cobrem toda esta linha. |
| SEG-08 / P2 | **A definir:** [User](../app/Models/User.php) não implementa `MustVerifyEmail`; rota com `verified` não impõe a confirmação nesse estado. | Cadastro público de contas removido e provisionamento interno implementado (E-12). Falta decidir se a confirmação de e-mail será obrigatória para contas internas e testar a política acordada. | Inspeção estática em 29/10/2026; decisão e testes pendentes. |
| SEG-09 / P1 | **Implementado:** FKs preservam autoria; [ProfileController](../app/Http/Controllers/ProfileController.php) converte restrição de exclusão em mensagem e mantém a sessão. | Testados histórico de contato, concessão revogada e conta sem vínculos; exclusão permitida não recria a conta ao encerrar a sessão. Desativação de funcionários bloqueia login, recuperação de senha e sessão aberta, sem excluir a conta (E-21). | E-01: schema. E-03 e E-04: fluxo web em SQLite e MariaDB, incluindo restrição de exclusão. E-21: desativação. |

## Segurança dos módulos e da operação

| ID / prioridade | Estado e escopo | Critério de verificação / próximo trabalho | Evidência |
| --- | --- | --- | --- |
| SEG-10 / P1 | **Implementado no recorte:** recebimento transacional com token de sessão; decisões transacionais com UUID, conteúdo, autoria, versões e travas nos pedidos selecionados. Contato abrangente e esclarecido exigido para concluir. | Replay, conflito de conteúdo, versões antigas, rollback por falha de auditoria, preservação do cadastro existente e correspondências pendentes cobertos. Ainda verificar concorrência real, inserções simultâneas e volume. | E-05 e E-07; testes de versões antigas não comprovam concorrência real entre conexões. |
| SEG-11 / P1 | **Implementado no recorte:** confirmação pública neutra sem identificadores; correspondências restritas ao atendimento. | Testada ausência de dados na confirmação, negativa de consulta interna e aceitação de novo pedido repetido sem exposição pública. Novas telas/exportações devem manter o escopo administrativo. | E-05; sem consulta pública por CPF/telefone/protocolo. |
| SEG-12 / P2 | **Relatório narrativo implementado** com leitura e escrita separadas (E-17, E-19). **Anexos de exames implementados** (E-22): disco privado sem rota de serviço, nomes gerados, tipo conferido pelo conteúdo (PDF, JPEG, PNG), 10 MB, download só com `consultar-exames`, `nosniff` e `no-store`, registros imutáveis. | Upload e download negados, executável/HTML renomeados, caminho manipulado e preservação dos anteriores testados. Pendentes: retenção e descarte, backup do diretório, varredura antivírus se exigida e verificação no servidor de publicação de que `storage/` não é servido. | E-22; [Contratos](backend/CONTRATOS.md#anexos-dos-exames-etapa-2--implementada-em-01112026). |
| SEG-13 / P2 | **Não verificado:** configuração do ambiente de publicação. [Sessão](../config/session.php) permite configurar cookies seguros; não comprova valor efetivo no servidor. | Antes de publicar: verificar HTTPS, debug desligado, cookies Secure/HttpOnly/SameSite, diretório público correto e ausência de exposição de `.env`, logs e backups. Conferir privilégios mínimos do banco e cabeçalhos adequados à interface. Registrar ambiente sem copiar segredos. | Pendente; não foi inspecionado ambiente de produção. |
| SEG-14 / P2 | **Não verificado:** vulnerabilidades conhecidas e atualização de dependências. | Auditar Composer e npm, registrar data/versões, resultado e aplicabilidade dos avisos. Após correção autorizada, repetir auditoria e testes afetados. Não executar atualizações automáticas indiscriminadas. | Auditorias não executadas nesta revisão; não há declaração de ausência de vulnerabilidades. |
| SEG-15 / P2 | **A definir:** proteção operacional dos dados, retenção, backups, logs e resposta a incidentes. | Definir acesso e retenção com a equipe responsável; evitar senhas/tokens/documentos em logs. Testar restauração em ambiente isolado e registrar quem pode consultar/exportar dados. Definir procedimento para incidente e revisão de acessos. | Pendente. Este documento não atesta conformidade legal. |
| SEG-16 / P1 | **Implementado:** guarda do teste de schema e helper em [TestCase](../tests/TestCase.php), chamado pelos testes que preparam banco nos módulos administrativo, agenda e relatórios. | Aceitam somente SQLite em memória ou `banco_de_leite_schema_test` local, em testing, sem URL/configuração em cache. Não presumir que classes antigas chamem o helper; conferir a seleção antes de execução destrutiva. | E-01, E-03, E-04 e E-05; sem teste negativo dedicado da guarda. |

## Evidências registradas

### E-22 — anexos de exames, 01/11/2026

- SEG-02 e SEG-12: envio exige `registrar-exames` (Gate antes e dentro da transação); download exige `consultar-exames`; sem login redireciona e sem permissão retorna 403.
- Conteúdo: tipo detectado pelo conteúdo; executável (`application/x-dosexec`) e HTML com extensão `.pdf`/`.png` recusados; limite de 10 MB. Nome original sem barras e caracteres de controle, nunca usado no caminho (`{avaliacao}/{uuid}.{ext}`).
- Armazenamento: disco `anexos` com `serve => false`; acesso direto a `/storage/...` respondeu 403 no servidor local. Respostas com `nosniff`, `no-store` e `Content-Disposition` controlado.
- Integridade: SHA-256, autor e instante gravados; model recusa alteração/exclusão; migration recusa rollback; arquivo órfão removido se a transação falhar; reenvio idempotente.
- Validação: `AnexoExameTest` (11 testes) em SQLite e MariaDB exclusivo; navegador automatizado com upload real (9 de 9). Detalhes em [Status](backend/STATUS.md#anexos-dos-exames-etapa-2--01112026).
- Limites: sem antivírus; retenção, descarte e backup dos arquivos não definidos; servidor de publicação não verificado (SEG-13). Revisão por outra pessoa pendente.

### E-20 — gestão de permissões, 29/10/2026

- SEG-01, SEG-02, SEG-04, SEG-05 e SEG-09: rotas `interno/permissoes` sob sessão e `gerenciar-permissoes` (somente admin) antes de consultar IDs; funcionário com `criar-funcionarios` recebe 403. Contas admin não são alvo (404). Responsável vem da sessão; campos extras ignorados. Motivo obrigatório e escapado no histórico; POST sem token retorna 419.
- Escrita delegada a `Permissao::conceder/revogar`, que reautoriza sob trava, grava concessão e evento na mesma transação e não duplica evento em repetição. O efeito na sessão aberta do funcionário foi confirmado em navegador.
- Validação: `PermissaoWebTest` (4 testes, 58 assertions) em SQLite; seleção com autorização e criação de funcionários no MariaDB exclusivo (24 testes, 146 assertions); fluxo Playwright/Edge com 6 cenários. Detalhes em [Status](backend/STATUS.md#gestão-de-permissões--29102026). Alteração de papel continua sem interface.

### E-21 — desativação de funcionários, 29/10/2026

- SEG-02, SEG-07 e SEG-09: conta desativada não autentica (credenciais com `desativado_em` nulo no login e no broker de senha, com a mesma mensagem genérica de senha errada, sem revelar a desativação); `EnsureInternalAccount` encerra a sessão aberta e responde 403; Gates negam as capacidades mesmo com concessões gravadas. Desativar e reativar exige admin, motivo e trava da conta, com evento auditado.
- Integridade operacional: desativação recusada enquanto houver visita futura sob responsabilidade da pessoa; agenda recusa responsável desativado. O rollback da migration é recusado com contas desativadas, para não reabrir o acesso.
- Validação: SQLite (desativação, permissões, operações de agenda e schema) e MariaDB exclusivo (60 testes, incluindo concorrência da agenda); fluxo Playwright/Edge com bloqueio, desativação, encerramento de sessão, login recusado e reativação. Detalhes em [Status](backend/STATUS.md#desativação-de-funcionários--29102026). Limite: a mensagem de login recusado aparece em inglês por falta de tradução (pendência de idioma registrada no Status).

### E-19 — interface de relatórios, 29/10/2026

- SEG-02, SEG-04, SEG-05, SEG-10, SEG-11 e SEG-16: rotas sob sessão/Gates antes de consultar IDs; leitura acessa somente versões publicadas, escrita inclui contexto próprio para registrar visita. Inputs são selecionados e passados aos métodos transacionais. CSRF aplicado às quatro rotas POST; nenhum GET cria atendimento ou rascunho.
- Narrativa/motivos escapados pelo Blade. Erros 422 e conflitos 409 de relatório preservam o texto somente na resposta autorizada, sem flash `_old_input`; respostas da área usam `private, no-store`. Revogação bloqueia o POST e a exposição da entrada. Conflitos não efetuam reenvio ou sobrescrita automática. Lista/histórico não carregam texto de versões em massa; texto aparece apenas na versão selecionada.
- Validação: seleção SQLite com 33 testes/290 assertions e seleção MariaDB exclusiva com 26 testes/175 assertions aprovadas. Casos e comandos em [Status](backend/STATUS.md#interface-web-dos-relatórios--29102026). Sem revisão visual/Alpine por ausência de navegador; teste HTTP não valida comportamento de cache de todos os clientes nem infraestrutura de produção.
- Próxima revisão: responsividade, acessibilidade, interação de salvar/finalizar, navegação e usabilidade dos conflitos. Nenhuma concessão automática, nova migration ou ativação externa nesta entrega. Upload/exportação e requisitos clínicos continuam fora do recorte.

### E-18 — ativação local dos relatórios, 29/10/2026

- SEG-09 e SEG-16: cinco migrations aplicadas com caminhos explícitos e somente `PermissaoSeeder` executado em `banco_de_leite_dev`, após autorização. Nenhuma recriação, fixture ou concessão automática. As duas contas e uma solicitação existentes foram preservadas; tabelas novas vazias e concessões continuam zeradas.
- Catálogo confirmado com cinco códigos; 20 migrations aplicadas, nenhuma pendente. A conexão inicialmente recusada foi restabelecida pelo usuário antes da aplicação. Evidência em [Status](backend/STATUS.md#ativação-local-dos-relatórios--29102026).
- Verificação local limitada a aplicação, status e contagens. Sem nova execução de testes, revisão visual ou validação de futuros endpoints de relatório. As evidências automatizadas anteriores permanecem com seus limites.

### E-17 — operações de relatório, 28/10/2026

- SEG-02, SEG-09, SEG-10 e SEG-16: Gates de leitura/escrita com admin explícito; revogação consultada no banco, incluindo replay; métodos de consulta retornam somente campos autorizados e leitura isolada não retorna rascunho. Operações fazem autorização antes da validação/consulta e sob trava.
- UUID/hash/revisão evitam repetição e sobrescrita desatualizada; realização e publicação são transacionais com auditoria obrigatória. Metadados operacionais não incluem texto/motivo narrativo. Versões recusam `save`/`delete` de instâncias existentes, mas operações privilegiadas sem eventos/SQL direto continuam fora dessa proteção. Não confundir com restrição do usuário do banco ou criptografia.
- Testes finais de operações/autorização: 31 testes/139 assertions em SQLite e MariaDB exclusivo. Execução MariaDB anterior incluindo concorrência e regressão de agenda: 56 testes/327 assertions. Suíte SQLite completa: 287 aprovados, 4 ignorados, 1.270 assertions. Comandos, sequência e limites em [Status](backend/STATUS.md#operações-e-permissões-de-relatório--28102026).
- Próximos reforços: proteger as futuras rotas, CSRF, validação de formulários/flash de conteúdo restrito, escape de narrativa e autorização de histórico. Fluxo web de relatório ainda não existe. Nenhuma ativação ou concessão no desenvolvimento; aplicar schema/catálogo antes de disponibilizar o código atualizado.

### E-16 — models de relatório e MariaDB, 28/10/2026

- SEG-02, SEG-09 e SEG-16: models mantêm bloqueio padrão de atribuição em massa e relações explícitas de autoria. Textos de relatório/rascunho, motivos narrativos e hash não aparecem em serialização automática, inclusive nas relações carregadas. `$hidden` não protege leitura direta em PHP nem substitui autorização.
- Schema validado no MariaDB exclusivo: **67 testes e 115 assertions aprovados**. A falha de conexão anterior foi resolvida. Models novos e regressão da base de agenda: **22 testes e 130 assertions aprovados em cada ambiente**, SQLite em memória e MariaDB exclusivo. Comandos e escopo em [Status](backend/STATUS.md#models-de-relatório-e-validação-mariadb--28102026).
- Sem migrations/seeders no desenvolvimento, concessões, novos endpoints ou dados reais. Permanecem pendentes Gates de relatório, transações, finalização/correções, imutabilidade na aplicação e testes concorrentes. Relações Eloquent e casts não validam coerência entre todos os vínculos nem habilitação profissional.

### E-15 — schema de relatório, 28/10/2026

- Itens SEG-09, SEG-10 e SEG-16, limitados à persistência. Cinco migrations novas e [RelatorioVisitaSchemaTest](../tests/Feature/RelatorioVisitaSchemaTest.php) implementam/testam FKs restritivas, unicidades e enums. Os eventos guardam metadados, sem colunas de narrativa; texto e motivo de correção ficam no relatório/versões. Isso não impede vazamento por código futuro nem torna registros imutáveis contra SQL direto.
- SQLite: schema novo e regressão de agenda aprovados (60 testes, 240 assertions); quatro classes de schema também passaram antes dos cinco casos finais de enum inválido (62 testes, 110 assertions). Evidências e comandos em [Status](backend/STATUS.md#schema-de-atendimento-e-relatório--28102026).
- MariaDB exclusivo: conexão recusada, inclusive na tentativa fora do sandbox; validação pendente. Nenhuma alteração no banco de desenvolvimento. Guarda de isolamento executada antes da preparação dos testes.
- Rollback da ampliação da agenda recusa histórico de realização. Rollbacks das tabelas novas são destrutivos; não são política de backup/recuperação. Permissões de leitura/escrita, imutabilidade das versões na aplicação, coerência entre referências, controle de concorrência e ausência de narrativa em logs/respostas continuam pendentes da implementação funcional. Não tratar esta evidência como aprovação de segurança do módulo.

### Planejamento de segurança do relatório — 28/10/2026 (sem implementação)

Contratos/Modelagem propõem acesso separado do atendimento administrativo/agenda, validação por registro, autorização antes de replay, CSRF, texto escapado, versões finalizadas imutáveis e auditoria sem cópia do conteúdo clínico. R1–R3 confirmadas pelo usuário: leitura separada de escrita/finalização, acesso automático do admin e atuação de escritores autorizados em relatórios de outros autores. Implementar a exceção do admin explicitamente para este módulo, sem bypass global para capacidades futuras. Manter autoria original e de cada alteração, motivo de correção e versões anteriores.

Testes planejados: matriz de acesso e revogação, exposição de texto entre módulos, relação atendimento/agendamento/versão-base, datas/fuso, finalização vazia, versões/replay, falha de auditoria e concorrência entre realização/cancelamento e salvamentos/finalizações. Na data, nenhum desses controles estava implementado; eles foram implementados e testados depois, conforme E-15 a E-19. Retenção, retificação de atendimento indevido e profissionais habilitados continuam pendentes.

### E-14 — ativação local da agenda, 28/10/2026

Aplicadas as quatro migrations de agenda em `banco_de_leite_dev`, lote 5, após autorização para avançar. Contagens anteriores/posteriores preservaram duas contas e uma solicitação, sem concessões automáticas; nenhuma migration pendente. `AgendaWebTest` e `AgendaConcurrencyTest` passaram no MariaDB exclusivo: **11 testes, 109 assertions**. Não houve recriação do desenvolvimento nem inserção de dados fictícios nele. Revisão visual não executada: ferramenta sem navegador disponível. Resultados e limites em [Status](backend/STATUS.md#ativação-local-da-agenda--28102026).

### E-13 — aplicação local das contas internas, 28/10/2026

Reexecução autorizada após ativação do servidor: migration de retirada aplicada isoladamente em `banco_de_leite_dev` e `PermissaoSeeder` executado. Consultas de leitura confirmaram um admin, um funcionário, zero contas de doadora, enum obrigatório com apenas os dois papéis internos e sem padrão, três permissões no catálogo e zero concessões. Quatro migrations da agenda continuam pendentes. Sem recriação do banco, `DatabaseSeeder`, concessões automáticas ou teste de interface. A falha de conexão de E-12 está resolvida para esta aplicação; detalhes e limitação de `db:table` em [Status](backend/STATUS.md#aplicação-das-contas-internas--28102026).

### E-12 — acesso exclusivamente interno, 27/10/2026

- Decisão explícita do usuário: sem contas/área autenticada de doadora, sem autocadastro; criação de funcionário por admin ou funcionário com permissão específica. Login e redefinição selecionam somente papéis internos; middleware web revalida existência/papel e encerra sessão inválida. Removidos controller/view/rotas de registro público, helper doadora, conta doadora do seeder e telas/links de demonstração.
- Nova capacidade `criar-funcionarios` com concessão/revogação auditada existente. A criação não aceita papel, senha conhecida, autoria ou permissões do cliente; grava autoria e gera senha aleatória não divulgada. Primeira definição pela recuperação por e-mail, sem autenticar a nova conta ou mudar a sessão do operador.
- Migration elimina contas antigas, sessões e tokens correspondentes; enum atual aceita apenas admin/funcionário sem padrão. FKs não são desativadas: vínculo histórico inesperado bloqueia a remoção e reverte a limpeza de sessões/tokens. Rollback estrutural não recupera contas apagadas.
- `InternalAccountsSchemaTest`, `FuncionarioCreationTest` e `InternalAccessTest`: **18 testes, 101 assertions aprovados no MariaDB exclusivo**. Suíte SQLite completa: **221 testes, 219 aprovados, 2 ignorados, 1.031 assertions**. Dois testes antigos foram adaptados ao novo domínio (admin não recebe concessão de funcionário; responsável inexistente é rejeitado). O teste de upgrade é o único que cria papel antigo, para comprovar sua remoção.
- A tentativa de ativação local foi recusada por conexão MySQL indisponível, inclusive fora do sandbox. Não houve exclusão no desenvolvimento nem execução do catálogo nessa tentativa. A revisão estática anterior contou uma conta por papel sem expor seus dados. Não houve teste de entrega real de e-mail ou revisão visual. Persistem definição de desativação/gestão de funcionários, proteção contra perda do último administrador e interface de concessões; nenhuma política foi inventada para esses pontos.
- Evidência em alterações locais e testes citados; Pint e suíte completos aprovados. Não representa auditoria exaustiva de todos os riscos do sistema.

### E-11 — integração web da agenda, 27/10/2026

- Itens: SEG-02, SEG-03, SEG-04, SEG-05, SEG-09, SEG-10 e SEG-16. Fontes: `AgendaController`, rotas `interno.agenda.*`, views `agenda.*` e `errors.409`, [AgendaWebTest](../tests/Feature/AgendaWebTest.php). Alterações locais ainda sem commit.
- As sete rotas exigem sessão e `gerenciar-agenda` antes de consultar IDs. A visão operacional não apresenta CPF, contatos/justificativas de análise ou informações clínicas; acesso a atendimento permanece separado. Revogação é verificada nas próximas requisições.
- Escritas passam somente campos conhecidos aos métodos transacionais existentes; autor, contexto e estado continuam controlados no servidor. Erros usam bags separados e preservam apenas entradas escalares permitidas. Formulários usam CSRF; histórico e texto livre usam saída escapada. Filtros têm valores/colunas fixos e datas locais convertidas em UTC.
- `AgendaWebTest`: 9 testes e 93 assertions aprovados no MariaDB exclusivo `banco_de_leite_schema_test`. Cobertura: sessão/permissão nas sete rotas, revogação, detalhe fora de estado/inexistente, isolamento de análise, escape HTML, contato/confirmação e retransmissão, campos técnicos ignorados, erros de formulário, conflito 409, reagendamento/cancelamento/não realização, filtro por período local e CSRF real com middleware ativo.
- A confirmação concorrente continua coberta pela evidência E-10; esta etapa não repetiu essa prova nem ampliou a garantia para todas as combinações concorrentes. Sem teste de carga, revisão visual ou homologação clínica. A ferramenta de UI retornou zero navegadores disponíveis. Resultados da regressão e limites em [Status](backend/STATUS.md#fluxo-web-da-agenda--27102026). Revisão por outra pessoa pendente.

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

### E-06 — Ativação local (24/10/2026)

- Com autorização do usuário, seis migrations aditivas e somente `PermissaoSeeder` foram executados em `banco_de_leite_dev`. Status final: dez migrations aplicadas, três contas, um item de catálogo, zero concessões e zero solicitações. Sem recriação do banco, alteração de credenciais ou concessão automática de acesso.
- Checks HTTP em servidor temporário usando exclusivamente o banco de testes: formulário e confirmação responderam 200; fila interna sem autenticação respondeu 302. Nenhum pedido foi inserido nessa verificação.
- Limites: navegador indisponível na ferramenta (inventário vazio); não houve revisão visual, login interativo ou teste de responsividade. Os testes automatizados anteriores não foram repetidos nesta ativação. Detalhes em [Status](backend/STATUS.md#ativação-local--24102026).

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
