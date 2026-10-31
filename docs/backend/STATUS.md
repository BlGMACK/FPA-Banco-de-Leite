# Status do backend

## Estado atual — 29/10/2026

Fonte do estado vigente. Conferido em 29/10/2026 e atualizado em 31/10/2026 (migration de desativação aplicada e mensagens em pt-BR) contra rotas, controllers, models, migrations e banco de desenvolvimento (somente status e contagens). As seções de [histórico](#histórico-de-verificações) registram cada etapa na data em que ocorreu e podem citar pendências já superadas; em caso de divergência, vale esta seção e o código.

| Área | Situação | Fonte |
| --- | --- | --- |
| Contas e sessão | **Implementado e aplicado.** Somente `admin` e `funcionario`; sem autocadastro nem conta de doadora. Login com limite de tentativas, recuperação de senha, perfil e exclusão com proteção de autoria. `EnsureInternalAccount` encerra sessões sem conta interna válida ou de conta desativada. **Desativação e reativação de funcionários** pelo admin, com motivo, permissões suspensas e bloqueio enquanto houver visitas futuras. Verificação de e-mail não é obrigatória (`User` não implementa `MustVerifyEmail`). | [rotas](../../routes/auth.php), [middleware](../../app/Http/Middleware/EnsureInternalAccount.php), [Contratos](CONTRATOS.md#contas-exclusivamente-internas--decisão-e-implementação-de-27102026) |
| Cadastro de funcionários | **Implementado.** Admin ou funcionário com `criar-funcionarios` cria funcionário com senha aleatória e autoria; a primeira senha é definida por recuperação via e-mail. Não concede permissões nem cria admin. | [FuncionarioController](../../app/Http/Controllers/FuncionarioController.php) |
| Permissões | **Implementado.** Catálogo com sete códigos: `atender-solicitacoes`, `gerenciar-agenda`, `criar-funcionarios`, `consultar-relatorios-visita`, `registrar-relatorios-visita`, `consultar-avaliacoes` e `avaliar-doadoras` (os dois últimos desde 31/10/2026, já no catálogo do desenvolvimento). Admin tem acesso automático, declarado por capacidade; funcionário depende de concessão. Escrita de relatório implica leitura; avaliar implica consultar avaliações e relatórios publicados. Tela `interno/permissoes`, somente para admin, concede e revoga com motivo obrigatório e mostra o histórico auditado. | [PermissaoController](../../app/Http/Controllers/PermissaoController.php), [Permissao](../../app/Models/Permissao.php), [Contratos](CONTRATOS.md#gestão-de-permissões-implementada-em-29102026) |
| Recebimento e fila | **Implementado.** Formulário público sem conta (`/doar`) e registro interno, confirmação neutra, limite de envios, fila paginada com alerta único de correspondências e detalhe restrito. | [Contratos](CONTRATOS.md#recebimento-e-fila-implementados-em-24102026) |
| Atendimento administrativo | **Implementado.** Início de análise, contatos, conferências, arquivamento de repetidos, admissão e área de acompanhamento, com versões, transação, autoria e histórico. **Não implementado:** correção dos dados do pedido. | [Contratos](CONTRATOS.md#atendimento-administrativo-implementado-em-26102026) |
| Agenda | **Implementado e aplicado.** Contatos de acompanhamento, confirmação, reagendamento, cancelamento, não realização e histórico; bloqueio de sobreposição por responsável; reconhece visita realizada e impede novo agendamento no mesmo acompanhamento. | [Contratos](CONTRATOS.md#fluxo-web-da-agenda-implementado-em-27102026) |
| Relatórios de visita | **Implementado e aplicado.** Registro da realização, rascunho, finalização, correções versionadas e histórico, com telas Blade próprias em `interno/relatorios`. Leitura vê somente versões publicadas; escrita independe da agenda. Roteiro executado em navegador automatizado em 29/10/2026 ([registro](#teste-das-telas-em-navegador--29102026)); avaliação de usabilidade pela equipe pendente. | [Contratos](CONTRATOS.md#interface-blade-implementada-em-29102026) |
| Análise do relatório (avaliação) | **Implementado e aplicado em 31/10/2026.** Avaliação criada na primeira publicação do relatório; decisões *Liberar para exames*, *Em aguardo* e *Não apta*, imutáveis e com histórico; decisão definitiva trava correções do relatório. Reserva da análise por 30 minutos e confirmação das decisões definitivas. Telas em `interno/avaliacoes`. | [Contratos](CONTRATOS.md#análise-do-relatório-avaliação--implementada-em-31102026) |
| Documentos/exames, decisão de habilitação e revogação | **Não implementado.** Estrutura acordada em 29/10/2026; faltam a lista real de exames, os prazos por tipo e a decisão D9. | [Roadmap](ROADMAP.md#avaliação-após-visita-exames-e-documentos--acordado-em-29102026) |
| Coleta, recebimento de leite, processamento, estoque e distribuição | **Não implementado.** Fora do primeiro fluxo. | [Roadmap](ROADMAP.md) |
| API REST de negócio | **Inexistente por decisão de arquitetura.** Integração por Blade, formulários e sessão. | [Arquitetura](../ARQUITETURA.md) |

### Banco de desenvolvimento

Conferência somente leitura em 29/10/2026 (`php artisan migrate:status` e contagens, sem exibir dados pessoais): 20 migrations aplicadas (lotes 1 a 6). Catálogo com cinco permissões e zero concessões; duas contas internas (uma admin); uma solicitação; tabelas de agenda, atendimento e relatório vazias.

Em 31/10/2026, com autorização do usuário, a 21ª migration (`2026_10_29_200000_add_desativacao_to_users_table`) foi aplicada no lote 7, após conferência do SQL com `--pretend`: **21 migrations aplicadas, nenhuma pendente**. Duas contas internas preservadas, nenhuma desativada ([registro](#ativação-da-desativação-e-mensagens-em-pt-br--31102026)).

**Ativação da avaliação (31/10/2026):** com autorização do usuário, após conferência do SQL com `--pretend`, as migrations `2026_10_31_062119_create_avaliacoes_table` e `2026_10_31_062120_create_decisoes_avaliacao_table` foram aplicadas no lote 8 e o `PermissaoSeeder` foi executado: **23 migrations aplicadas, nenhuma pendente**; catálogo com sete permissões e zero concessões; duas contas preservadas. Não havia relatório publicado, então nenhuma avaliação foi preenchida.

**Pendente:** a migration `2026_10_31_200000_add_reserva_analise_to_avaliacoes_table` (reserva da análise, 31/10/2026) ainda não foi aplicada no desenvolvimento.

### Verificação automatizada

| Data e ambiente | Comando | Resultado |
| --- | --- | --- |
| 31/10/2026, PHP 8.5.10, Laravel 13.34.0, SQLite `:memory:` (`phpunit.xml`) | `php artisan test` | **342 testes: 338 aprovados, 4 ignorados; 1.653 assertions** ([registro](#análise-do-relatório-avaliação--31102026)). |
| 29/10/2026, PHP 8.5.10, Laravel 13.34.0, SQLite `:memory:` (`phpunit.xml`) | `php artisan test` | **312 testes: 308 aprovados, 4 ignorados; 1.539 assertions.** Os quatro ignorados são os testes de concorrência de agenda e relatório, que exigem MariaDB; os de agenda passaram no MariaDB exclusivo na mesma data ([desativação](#desativação-de-funcionários--29102026)). |
| 29/10/2026 | `npm.cmd run build` | Aprovado (registrado na [preparação da revisão manual](#preparação-da-revisão-manual--29102026)). |

A suíte completa não foi repetida em MariaDB nesta conferência. As execuções MariaDB por módulo, com seus limites, estão no histórico. Não há CI configurado.

### Validações pendentes

- **Avaliação de usabilidade pela equipe.** O fluxo completo (pedido público → admissão → agenda → relatório) e o roteiro dos relatórios foram executados em navegador automatizado em 29/10/2026, sem defeitos funcionais ([registro](#teste-das-telas-em-navegador--29102026)). Faltam o julgamento humano das telas, leitor de tela, outros navegadores e dispositivos reais, além das sugestões de melhoria registradas. Atendimento e agenda foram exercitados no caminho feliz, sem revisão detalhada de erros.
- Concorrência coberta apenas nos cenários testados; sem teste de carga ou volume real.
- Entrega real de e-mail (SMTP) não validada; necessária para a primeira senha de novos funcionários.
- Ambiente de publicação, backups e restauração não verificados ([SEG-13](../SEGURANCA.md#segurança-dos-módulos-e-da-operação), [SEG-15](../SEGURANCA.md#segurança-dos-módulos-e-da-operação)).

### Pendências de implementação ou decisão

- Correção de dados do pedido após o recebimento.
- Documentos/exames e anexos, decisão de habilitação e sua revogação. Estrutura acordada em 29/10/2026 ([Roadmap](ROADMAP.md#avaliação-após-visita-exames-e-documentos--acordado-em-29102026)); faltam a lista real de exames, os prazos e a decisão D9.
- Nova visita durante *Em aguardo*: a agenda bloqueia novo agendamento após visita realizada; definir se uma nova visita gera nova avaliação ou complementa a existente.
- Política de verificação de e-mail ([SEG-08](../SEGURANCA.md#controles-existentes-e-verificações-pendentes)).
- `DatabaseSeeder` usa `create` com e-mails e senha fixos de demonstração e não é idempotente; scripts `setup` e `post-create-project-cmd` do Composer geram chave e mencionam SQLite.
- Middleware `role` (`EnsureUserHasRole`) está registrado, mas nenhuma rota o utiliza; a autorização efetiva é por Gates. Decidir se permanece.
- Retenção, homologação pelo serviço, responsáveis e prioridades continuam a definir ([Roadmap](ROADMAP.md)).

### Roteiro pendente para a equipe

Usar dados fictícios e uma conta interna com escrita autorizada (admin possui acesso automático; funcionários recebem acesso em “Permissões”). Não criar acesso público para facilitar o teste.

1. Com uma solicitação admitida e visita confirmada, abrir “Relatórios de visita” e registrar uma visita já ocorrida. Conferir nome, local, datas em horário de Marília e criação de rascunho, sem aprovação automática.
2. Digitar um relato. Conferir que “Finalizar texto salvo” fica desabilitado enquanto o texto não foi salvo; salvar, recarregar e confirmar o conteúdo antes de finalizar.
3. Iniciar uma correção com motivo, salvar e finalizar. Conferir que a versão anterior continua consultável e que a indicação de versão vigente/histórica está clara.
4. Abrir o mesmo rascunho em duas abas. Salvar na primeira e tentar salvar na segunda. Conferir mensagem de conflito, texto rejeitado disponível para cópia e ausência de sobrescrita. Copiar antes de recarregar.
5. Repetir a navegação com viewport estreita e teclado: menu, filtros, campos, mensagens, botões e histórico. Registrar problemas de rolagem, leitura, foco ou acionamento.

Leitura isolada, revogação e CSRF já têm evidências automatizadas. Os cinco passos foram executados em navegador automatizado em 29/10/2026 ([registro](#teste-das-telas-em-navegador--29102026)); a equipe ainda deve repeti-los para avaliar a usabilidade. Documentos/exames continuam dependendo de especificação funcional própria.

## Histórico de verificações

Registros em ordem cronológica inversa. Cada um descreve o estado **na data indicada**: referências a etapas “pendentes” ou “próximas” foram superadas conforme o [estado atual](#estado-atual--29102026).

### Reserva da análise e confirmação das decisões definitivas — 31/10/2026

A pedido do usuário, a partir das observações do teste em navegador abaixo. Migration `2026_10_31_200000_add_reserva_analise_to_avaliacoes_table` (três colunas anuláveis em `avaliacoes`; rollback só remove as colunas), métodos `iniciarAnalise`/`encerrarAnalise` em `Avaliacao`, bloqueio da decisão e da abertura de correção do relatório durante reserva alheia, duas rotas novas, botão “Iniciar análise”, aviso na lista e no detalhe, caixa de confirmação para *Liberar para exames* e *Não apta* e exibição do motivo não registrado em conflito. Contrato em [Contratos](CONTRATOS.md#análise-do-relatório-avaliação--implementada-em-31102026).

| Verificação executada | Resultado |
| --- | --- |
| `php artisan test`, SQLite `:memory:` | 351 testes: 347 aprovados, 4 ignorados; 1.709 assertions. Novos casos: confirmação obrigatória só nas definitivas, reserva bloqueando outro profissional (início, decisão e correção), liberação pela decisão, encerramento só pelo dono, vencimento, recusa com correção aberta ou análise concluída, telas, 403 para leitora e CSRF nas rotas novas. |
| Testes de avaliação, relatórios (operações, web, concorrência) e `EmployeeAuthorizationTest`, MariaDB `banco_de_leite_schema_test` | 85 testes, 490 assertions aprovados. |
| Playwright/Edge headless, servidor temporário em `banco_de_leite_schema_test` | 10 de 10: só “Iniciar análise” antes da reserva; formulário e aviso após; confirmação oculta sem resultado e em *Em aguardo*, visível em *Liberar para exames*; navegador impede envio sem marcar; outra avaliadora vê a reserva sem botões; lista mostra “Em análise por”; decisão definitiva registrada; encerrar libera. Sem erros de console. |
| `vendor/bin/pint --dirty --format agent` | Aprovado. |

**Migration ainda não aplicada em `banco_de_leite_dev`:** aplicar com `php artisan migrate` (conferir antes com `--pretend`) antes de usar as telas no desenvolvimento.

### Teste das telas de avaliação em navegador — 31/10/2026

A pedido do usuário, mesmo método de [29/10/2026](#teste-das-telas-em-navegador--29102026): Playwright (`playwright-core` 1.56.1) em pasta temporária fora do projeto, Microsoft Edge headless, servidor `php -S` com `DB_DATABASE=banco_de_leite_schema_test`, sem tocar em `banco_de_leite_dev` nem no `.env`. Dados fictícios só nesse banco: `PermissaoSeeder`, uma conta admin, uma funcionária com `avaliar-doadoras`, outra só com `consultar-avaliacoes`, uma visita confirmada já ocorrida e três avaliações de fábrica (uma em aguardo, com `<script>` no motivo).

| Cenário | Resultado |
| --- | --- |
| Registro da visita, rascunho e finalização do relatório | A avaliação nasce na finalização e aparece na lista como *Aguardando análise*; menu “Avaliações” ativo. |
| *Em aguardo* sem motivo, depois com motivo | 422 com mensagem em português e opção mantida; em seguida decisão registrada no histórico, formulário ainda disponível. |
| Correção do relatório durante *Em aguardo* | Avaliação informa a correção em elaboração e oculta o formulário; após finalizar a correção, a decisão passa a valer para a versão 2. |
| *Liberar para exames* e conflito entre abas | Análise concluída; a aba desatualizada recebe 409 com mensagem e link “Recarregar a situação atual”; após recarregar, situação atual sem formulário. O relatório deixa de oferecer correção. |
| Filtro por situação | Retorna somente a situação escolhida; valor inválido volta com validação, sem erro de servidor. |
| Avaliadora | Registra *Não apta*; lê o relatório publicado sem formulários de escrita; menu Painel, Relatórios de visita e Avaliações; agenda responde 403. |
| Leitora | Sem formulário de decisão nem link para o relatório; menu Painel e Avaliações; `<script>` do motivo exibido como texto, sem diálogo. |
| Celular (390 px) e teclado | Sem rolagem horizontal na lista, no detalhe concluído e no formulário; ordem de Tab lógica (pular para o conteúdo → menu → voltar → ler relatório → resultado → motivo → registrar), com foco visível. |

Console sem erros JavaScript; apenas os 422, 409 e 403 esperados. Limites iguais aos de 29/10/2026: não substitui avaliação humana, leitor de tela, outros navegadores ou dispositivo real.

Observações para decisão da equipe, sem defeito funcional (ambas tratadas no mesmo dia, ver registro acima):

- Quando o conflito ocorre porque a outra aba já concluiu a análise, o formulário some e o motivo digitado não é exibido. Na prática não há nova decisão possível, mas quem digitou perde o texto sem aviso.
- *Liberar para exames* e *Não apta* são definitivas e travam a correção do relatório, mas são registradas com um clique, sem etapa de confirmação.

### Análise do relatório (avaliação) — 31/10/2026

Implementação autorizada pelo usuário após as decisões de 31/10/2026 ([Roadmap](ROADMAP.md#avaliação-após-visita-exames-e-documentos--acordado-em-29102026)). Migrations `avaliacoes` e `decisoes_avaliacao` (com preenchimento das avaliações de relatórios já publicados e rollback recusado havendo decisões), models `Avaliacao` e `DecisaoAvaliacao` com factories, permissões `consultar-avaliacoes` e `avaliar-doadoras` (Gates, seeder, tela de permissões), criação da avaliação na primeira finalização do relatório, trava de correção após decisão definitiva, `AvaliacaoController`, views `avaliacoes/{index,show}`, menu “Avaliações” e aviso na tela do relatório. Contrato em [Contratos](CONTRATOS.md#análise-do-relatório-avaliação--implementada-em-31102026).

| Verificação executada | Resultado |
| --- | --- |
| `AvaliacaoOperationsTest`, `AvaliacaoSchemaTest`, `AvaliacaoWebTest`, SQLite `:memory:` | 29 testes aprovados: matriz de permissões, criação única, transições, motivo obrigatório, replay/versão, revogação, imutabilidade, trava de correção, espera durante correção, preenchimento da migration, unicidade, rollback recusado, rotas/403/404, escape, 422, 409, CSRF e menu. |
| `php artisan test`, SQLite `:memory:` | 342 testes: 338 aprovados, 4 ignorados; 1.653 assertions. `EmployeeAuthorizationTest` passou a esperar sete permissões no catálogo. |
| Testes de avaliação, operações/web/concorrência de relatórios e `EmployeeAuthorizationTest`, MariaDB `banco_de_leite_schema_test` | 76 testes, 434 assertions aprovados. |
| `vendor/bin/pint --dirty --format agent` | Aprovado. |

Ativação no desenvolvimento registrada em [Banco de desenvolvimento](#banco-de-desenvolvimento). Limites: telas revisadas em navegador automatizado no mesmo dia ([registro](#teste-das-telas-de-avaliação-em-navegador--31102026)); não há teste de concorrência específico entre decisão e abertura de correção (a ordem de travas é a mesma da correção, verificada por leitura do código).

### Ativação da desativação e mensagens em pt-BR — 31/10/2026

Autorizado pelo usuário nesta data. A migration de desativação foi aplicada em `banco_de_leite_dev`, encerrando a pendência de 29/10/2026 (login local falhando por falta de `users.desativado_em`). O idioma passou a pt-BR: `config/app.php` usa `pt_BR` como padrão, `.env.example` define `APP_LOCALE=pt_BR` (o fallback continua `en`) e foram criados `lang/pt_BR/{auth,passwords,pagination,validation}.php` e `lang/pt_BR.json`, a partir dos arquivos em inglês do Laravel 13.34.0. A tradução cobre login recusado e limite de tentativas, recuperação de senha, validação genérica (com nomes legíveis dos campos usados nos formulários), paginação, e-mails de recuperação e verificação e páginas de erro do framework. As `messages()`/`attributes()` dos form requests continuam prevalecendo. Quem já tem `.env` local deve ajustar `APP_LOCALE=pt_BR`.

| Verificação executada | Resultado |
| --- | --- |
| `php artisan migrate --pretend` e `php artisan migrate`, `banco_de_leite_dev` | SQL somente aditivo; aplicada no lote 7. `migrate:status`: 21 aplicadas, nenhuma pendente. Contagem: 2 contas, 0 desativadas. |
| `AuthenticationTest` | Novo teste de mensagens de validação em português e asserção da mensagem de login recusado (“E-mail ou senha incorretos.”). |
| `php artisan test`, SQLite `:memory:` | 313 testes: 309 aprovados, 4 ignorados; 1.544 assertions. |
| `vendor/bin/pint --dirty --test` | Aprovado. |

Limites: o texto dos e-mails não foi conferido em entrega real; a mensagem `passwords.user` mantém o comportamento do framework (informa quando o e-mail não existe), apenas traduzida. As telas não foram revisadas em navegador nesta etapa.

### Desativação de funcionários — 29/10/2026

Implementação das regras acordadas ([Roadmap](ROADMAP.md#desativação-de-funcionários--acordado-em-29102026)), após o usuário aprovar o desenho técnico. Migration aditiva `2026_10_29_200000_add_desativacao_to_users_table` (`users.desativado_em`, `users.desativado_por_user_id` e tabela `eventos_conta`), model `EventoConta`, operações `User::desativar/reativar` (Gate `gerenciar-permissoes`, trava da conta, evento com motivo), duas rotas em `interno/permissoes` e bloco “Situação da conta” na tela. Login, recuperação de senha, `EnsureInternalAccount` e Gates negam contas desativadas; concessões permanecem gravadas e voltam a valer na reativação. A agenda não aceita responsável desativado, e a tela de agenda deixa de oferecê-lo. O registro de visita já ocorrida continua aceitando o responsável previsto, mesmo desativado. Filtros de histórico continuam listando todos. O rollback da migration é recusado enquanto houver conta desativada, para não liberar o acesso dela.

| Verificação executada | Resultado |
| --- | --- |
| `DesativacaoFuncionarioTest`, `AgendaOperationsTest` e `PermissaoWebTest`, SQLite `:memory:` | 30 testes, 215 assertions aprovados. |
| `InternalAccountsSchemaTest`, SQLite `:memory:` | 3 testes aprovados, incluindo recusa de rollback e preservação de autoria. |
| `php artisan test`, SQLite `:memory:` | 312 testes: 308 aprovados, 4 ignorados. |
| Seleção de schema, desativação, permissões, operações e concorrência da agenda, acesso interno e relatórios web, MariaDB `banco_de_leite_schema_test` | 60 testes, 457 assertions aprovados. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado. |
| Playwright/Edge contra o banco de testes | Visita futura: aviso com a visita e botão desabilitado. Depois de mover a visita para o passado: desativação com permissões “Suspensa”; sessão aberta da funcionária recebe 403 e é encerrada; novo login recusado com mensagem genérica; reativação registrada no histórico, com login e agenda funcionando de novo. |

A primeira versão da view falhou na renderização: o `@php(...)` em linha, num arquivo com blocos `@php ... @endphp`, é casado com o `@endphp` seguinte. Foi corrigida para a forma em bloco. Não aplicado em `banco_de_leite_dev`: a ativação depende de autorização.

### Gestão de permissões — 29/10/2026

Implementação autorizada pelo usuário: `PermissaoController`, quatro rotas em `interno/permissoes` sob `gerenciar-permissoes` (somente admin), duas views, item “Permissões” no menu e atalho após cadastrar funcionário. A tela usa os métodos auditados já existentes em `Permissao`; não houve mudança de model, schema, migration ou seeder. Contrato em [Contratos](CONTRATOS.md#gestão-de-permissões-implementada-em-29102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/PermissaoWebTest.php`, SQLite `:memory:` | 4 testes, 58 assertions aprovados. |
| `php artisan test`, SQLite `:memory:` | 307 testes: 303 aprovados, 4 ignorados (concorrência MariaDB); 1.478 assertions. |
| `PermissaoWebTest`, `EmployeeAuthorizationTest` e `FuncionarioCreationTest`, MariaDB `banco_de_leite_schema_test` | 24 testes, 146 assertions aprovados. |
| `php vendor/bin/pint --dirty --format agent` e `php artisan route:list --path=interno/permissoes` | Aprovado; quatro rotas registradas. |
| Playwright/Edge contra o banco de testes | 6 de 6 cenários: funcionária sem permissão não vê módulos (403); admin concede agenda com motivo; efeito imediato na sessão já aberta da funcionária; motivo vazio bloqueado no navegador; revogação volta a negar acesso e aparece no histórico; sem rolagem horizontal a 390 px. |

Os testes HTTP cobrem: autorização antes da busca de IDs (visitante, funcionário com `criar-funcionarios`, admin), contas admin fora do alcance (404), motivo obrigatório inclusive só com espaços, limite de 2.000 caracteres, permissão inexistente, repetição sem novo evento, campos extras ignorados, escape do motivo no histórico e CSRF. Nenhuma concessão foi feita em `banco_de_leite_dev`. A desativação de funcionários foi entregue depois, na seção acima.

### Teste das telas em navegador — 29/10/2026

A pedido do usuário, Playwright 1.56.1 instalado numa pasta temporária fora do projeto (sem alterar `package.json`), usando o Microsoft Edge do sistema em modo headless. Servidor PHP temporário (`php -S`, executado em `public/`) com `DB_DATABASE=banco_de_leite_schema_test`, sem tocar em `banco_de_leite_dev` nem no `.env`. Dados fictícios criados só nesse banco: `PermissaoSeeder`, uma conta admin e uma funcionária com apenas `consultar-relatorios-visita`.

| Cenário | Resultado |
| --- | --- |
| Pedido público em `/doar` (desktop) | Confirmação neutra exibida. |
| Login admin, fila, início de análise, contato e admissão | Aprovado. |
| Agenda: contato de acompanhamento e confirmação da visita | Aprovado; a visita aparece em “Visitas a registrar”. |
| Roteiro 1: registro da visita realizada | Relatório criado em rascunho; tela informa que não aprova a doação. |
| Roteiro 2: salvar antes de finalizar | “Finalizar texto salvo” desabilitado após digitar sem salvar e habilitado após salvar; texto preservado após recarregar; publicação da versão 1. |
| Roteiro 3: correção versionada | Versão 2 publicada; versão 1 consultável com o texto original. |
| Roteiro 4: conflito entre abas | Rascunho × rascunho: 409, texto rejeitado mantido no editor somente leitura, botões desabilitados, sem sobrescrita (conferido no banco). Finalização × rascunho: 409 e texto enviado na área separada para cópia. |
| Roteiro 5: teclado e celular (390 px) | Ordem de Tab lógica, com foco visível em todos os elementos; sem rolagem horizontal em `/doar`, fila, agenda, lista e relatório; menu recolhido abre. |
| Leitura isolada | Funcionária vê somente a versão publicada, sem editor, rascunho ou motivo em elaboração; menu restrito; `/interno/agenda` responde 403. |

Console do navegador sem erros JavaScript; apenas os 409 e 403 esperados. Limites: navegador automatizado não substitui avaliação humana, leitor de tela, Firefox/Safari ou dispositivo real. Atendimento e agenda foram cobertos apenas no caminho feliz.

Sugestões de usabilidade, sem defeito funcional, para decisão da equipe:

- No registro da visita, início e fim efetivos vêm vazios, embora o horário previsto apareça logo acima; pré-preencher reduziria digitação.
- No conflito rascunho × rascunho, o texto rejeitado fica no editor com o rótulo “Relato da visita”, o que pode ser confundido com o rascunho salvo; a variante após finalização usa o rótulo “Texto enviado e não salvo”.
- Num relatório novo e vazio, “Finalizar texto salvo” fica habilitado; o backend recusa a finalização vazia, mas o botão poderia indicar isso antes.
- Não há link “pular para o conteúdo”: são oito paradas de Tab no menu antes do conteúdo da página.

**Implementação das sugestões, a pedido do usuário (mesmo dia):** as quatro foram aplicadas nas views ([Contratos](CONTRATOS.md#interface-blade-implementada-em-29102026)), sem mudança de backend, rotas ou schema. Verificações:

| Comando / ambiente | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaWebTest.php`, SQLite `:memory:` | 12 testes, 150 assertions aprovados, incluindo o novo teste das quatro mudanças. |
| `php artisan test`, SQLite `:memory:` | 303 testes: 299 aprovados, 4 ignorados (concorrência MariaDB); 1.420 assertions. |
| `RelatorioVisitaWebTest` e `RelatorioVisitaAuthorizationTest`, MariaDB `banco_de_leite_schema_test` | 27 testes, 190 assertions aprovados. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado. |
| Fluxo Playwright/Edge repetido com as asserções novas | 12 de 12 cenários aprovados. A primeira versão do link de pular ficava atrás da barra de navegação (`.navbar` tem `position: relative`); corrigida com `z-3` e conferida por screenshot e `elementFromPoint`. |

### Preparação da revisão manual — 29/10/2026

Após autorização para a próxima etapa, conferidos o layout `app.blade.php`, a entrada `resources/js/app.js`, as views de relatório e o controller. O layout carrega CSS/JS via Vite; a entrada importa Bootstrap, disponibiliza Alpine e executa `Alpine.start()`. Não houve alteração de código nesta revisão.

Executado `npm.cmd run build`: **aprovado**, Vite 8.3.2, 62 módulos transformados e manifesto/assets gerados. A primeira tentativa com `npm` foi impedida pela política de execução do wrapper PowerShell `npm.ps1`; usar o executável `npm.cmd` funcionou sem alterar a política do sistema, instalar ou atualizar dependências. Build confirma empacotamento, não interação no navegador.

O inventário da ferramenta de UI novamente retornou zero apps/navegadores. Foi solicitado ao usuário testar o fluxo; **resultado manual ainda não recebido**. Os testes HTTP anteriores não foram repetidos porque não houve mudança de comportamento. Sem migrations, seeders, alterações de dados ou commits.

### Interface web dos relatórios — 29/10/2026

Implementados `RelatorioVisitaController`, sete rotas protegidas, três views Blade/Bootstrap e item de navegação. Lista própria, filtros/paginação, registro de realização, rascunho, publicação, correção e histórico usam os métodos existentes. Leitura não recebe rascunhos; escrita não depende de agenda. Contrato em [Contratos](CONTRATOS.md#interface-blade-implementada-em-29102026).

| Verificação executada | Resultado |
| --- | --- |
| `php artisan route:list --path=interno/relatorios --no-interaction` | Sete rotas identificadas. |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaWebTest.php tests/Feature/AgendaWebTest.php tests/Feature/InternalAccessTest.php`, SQLite `:memory:` | **33 testes, 290 assertions aprovados**. |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaWebTest.php tests/Feature/RelatorioVisitaAuthorizationTest.php --stop-on-error`, MariaDB `banco_de_leite_schema_test` | **26 testes, 175 assertions aprovados**, após ajuste do estado inicial Alpine em respostas de validação. |
| `php vendor/bin/pint --dirty --format agent` e `git diff --check` | Aprovados. |

Processos de teste com `APP_ENV=testing`, conexão/banco explícitos e `DB_URL` vazio; guarda de isolamento antes da preparação. Onze novos testes HTTP cobrem autenticação/Gates antes de IDs, leitura sem rascunho, HTML escapado, realização sem depender da agenda, fluxo completo e replay, histórico, campos ignorados, 422/409 com preservação de texto somente na resposta, revogação, CSRF nas quatro escritas, filtros/navegação, finalização vazia e compromisso cancelado. Respostas restritas usam no-store. A suíte completa não foi repetida; selecionadas as rotas afetadas e regressões de navegação/acesso.

Inventário de UI retornou zero apps/navegadores; **não houve revisão visual, responsividade ou execução real de Alpine**. O bloqueio visual de finalização com texto não salvo precisa dessa conferência; o backend sempre publica somente a revisão persistida. Nenhuma migration/seeder no desenvolvimento, concessão, dado fictício local, alteração de dependências ou commit nesta entrega. Próximo passo: revisão manual das telas e ajustes de usabilidade antes de avançar aos requisitos de documentos/exames ainda não definidos pelo Banco.

### Ativação local dos relatórios — 29/10/2026

Com autorização do usuário para aplicar migrations/catálogo e fazer commits, executadas as cinco migrations `2026_10_28_183700` a `183704` com `php artisan migrate --path=...` (cinco caminhos explícitos) e `--no-interaction`. Executado somente `php artisan db:seed --class=PermissaoSeeder --no-interaction`. A primeira consulta falhou por conexão recusada, inclusive fora do sandbox; após o usuário iniciar o servidor, a aplicação concluiu normalmente.

`migrate:status --no-interaction` confirmou **20 migrations aplicadas, nenhuma pendente**, com as cinco novas no lote 6. Contagens antes/depois em `banco_de_leite_dev`: duas contas internas, uma solicitação e zero interessadas, contatos, agendamentos, reservas e eventos de agenda. As quatro tabelas novas estão vazias. O catálogo passou de três para cinco permissões; `permissao_user` continuou vazio. Códigos confirmados: atendimento, agenda, criação de funcionários, consulta de relatórios e registro de relatórios.

Não houve recriação do banco, `migrate:fresh`, `DatabaseSeeder`, inserção de fixtures, concessão automática ou mudança de `.env`/dependências. A verificação local foi de aplicação/status e contagens, sem exibir dados pessoais; não substitui revisão visual ou exercício manual. Os resultados automatizados da etapa anterior continuam registrados abaixo; sem alteração de PHP nesta ativação, não foram repetidos. `git diff --check` aprovado, com avisos somente de normalização de fim de linha.

O schema já permite usar a agenda atualizada. O backend de relatórios existe, mas suas telas/rotas continuam pendentes. A autorização atual de commit abrange as alterações da etapa anterior, separadas entre permissões, operações/integração e documentação; não inclui push.

### Operações e permissões de relatório — 28/10/2026

Implementados os Gates/catálogo de leitura e escrita, consultas autorizadas, realização da visita, salvamento de rascunho, finalização e abertura/finalização de correção. Agenda reconhece `realizado`, remove atendidas da fila e bloqueia nova confirmação nesse acompanhamento. Narrativas/motivos restritos não entram no histórico operacional. Contrato e limites em [Contratos](CONTRATOS.md#backend-implementado-após-autorização-em-28102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit`, SQLite `:memory:` | **291 testes: 287 aprovados, 4 ignorados; 1.270 assertions**. Os quatro ignorados exigem MariaDB para concorrência real (dois de agenda e dois de relatório). |
| `RelatorioVisitaOperationsTest`, `RelatorioVisitaAuthorizationTest`, `RelatorioVisitaConcurrencyTest`, `AgendaOperationsTest`, `AgendaWebTest`, MariaDB exclusivo, via `php vendor/bin/phpunit ... --stop-on-error` | **56 testes, 327 assertions aprovados**, antes dos oito cenários adicionais de revisão/auditoria/autorizações. |
| Reexecução final de `RelatorioVisitaOperationsTest` e `RelatorioVisitaAuthorizationTest`, SQLite e MariaDB exclusivo | **31 testes, 139 assertions aprovados em cada ambiente**, incluindo os oito casos adicionais. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado após ajustes automáticos. |

Os processos usam `APP_ENV=testing`, `DB_URL` vazio e conexão/banco explícitos: SQLite em memória ou `banco_de_leite_schema_test` local, com guarda antes da preparação. Concorrência usa processos/conexões independentes e barreira por trava: duas finalizações e cancelamento contra realização produziram uma conclusão e um 409 por disputa. Não é teste de carga nem prova de todas as interleavings. Falhas simuladas de auditoria confirmam reversão de realização e finalização. Testes cobrem datas/fuso, motivo de divergência, reenvio, revisão vencida, limites, revogação, matriz de acesso e não exposição na agenda.

Nenhuma migration/seeder executada no desenvolvimento, nenhuma concessão, alteração de `.env`, dependências ou commit nesta etapa. **A agenda atualizada exige as migrations de relatório antes do uso local**; ativação ainda pendente. Não há telas/rotas de relatório; não houve revisão visual. A proteção de versões por eventos Eloquent não impede SQL direto ou operações que ignorem eventos. Não foi implementada aprovação clínica.

### Models de relatório e validação MariaDB — 28/10/2026

O usuário autorizou repetir a validação, implementar os models e fazer commits por finalidade. Implementados `Atendimento`, `RelatorioVisita`, `VersaoRelatorioVisita` e `EventoAtendimento`, suas factories, relações/casts e `Agendamento::atendimento()`. Campos narrativos/motivos e hash de evento são omitidos na serialização padrão; atribuição em massa segue bloqueada. Não foram implementados Gates, operações transacionais ou telas de relatório. Detalhes em [Modelagem](MODELAGEM.md#persistência-proposta-para-atendimento-e-relatório--28102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaSchemaTest.php tests/Feature/AgendaSchemaTest.php tests/Feature/AdministrativeSchemaTest.php tests/Feature/InternalAccountsSchemaTest.php --stop-on-error`, MariaDB exclusivo | **67 testes, 115 assertions aprovados**. Resolve a pendência de conexão/validação registrada abaixo. |
| `php vendor/bin/phpunit tests/Feature/RelatorioVisitaModelsTest.php tests/Feature/AgendaModelsAuthorizationTest.php`, SQLite `:memory:` | **22 testes, 130 assertions aprovados**. |
| Mesmas classes de models com `--stop-on-error`, MariaDB exclusivo | **22 testes, 130 assertions aprovados**. |
| `php vendor/bin/pint --dirty --format agent` e `git diff --check` | Aprovados; avisos de normalização CRLF/LF não são falhas. |

Todos os testes rodaram com `APP_ENV=testing`, conexão e banco explícitos no processo e `DB_URL` vazio. MariaDB usou somente `banco_de_leite_schema_test`; SQLite usou memória. Não foram aplicados migrations/seeders no desenvolvimento, não houve alteração de `.env`, dependências ou concessões. As factories são fixtures, não operações de negócio nem seeders de dados reais.

Cobertura nova dos models: vínculos até a identidade original, autores distintos, casts de datas/números, relações de base/correções/eventos, ausência de `updated_at` em evidências, bloqueio de atribuição em massa e omissão de narrativa na serialização com relações carregadas. Não comprova finalização, imutabilidade contra escrita explícita, permissões do relatório ou concorrência; esses fluxos ainda não existem. Próximo recorte: autorização de leitura/escrita conforme os acordos, seguida das operações transacionais e integração com a fila/agenda.

### Schema de atendimento e relatório — 28/10/2026

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

### Especificação de atendimento e relatório — 28/10/2026

Detalhados contratos, proposta de persistência, transições, integração à fila/agenda e cenários de teste. Conferidos no código: `Agendamento` ainda não registra realização; a fila usa ausência de reserva; `Permissao` aceita somente três códigos administrativos e concessões apenas a funcionários; `User` dá acesso administrativo automático a admin. Essas regras não foram ampliadas silenciosamente para relatórios.

Questões R1–R3 respondidas pelo usuário em 28/10/2026: leitura separada da escrita/finalização, acesso automático do admin e continuidade/correção por qualquer profissional autorizado. Matriz de acesso atualizada em Contratos, distinguindo acordos de detalhes técnicos propostos. Origem dos acordos e ausência de PDF identificadas. Alterações somente em Markdown; sem testes de aplicação, migrations, seeders ou mudanças no banco nesta especificação. As verificações anteriores da agenda não validam o módulo proposto. Próximo recorte: preparar migrations e testes isolados após autorização de implementação.

### Ativação local da agenda — 28/10/2026

Após autorização para o próximo passo, aplicadas exclusivamente as quatro migrations da agenda com `php artisan migrate` e quatro opções `--path` explícitas, sem recriação do banco ou execução de seeders. `migrate:status --no-interaction` confirmou todas as 15 migrations aplicadas; as quatro da agenda estão no lote 5.

Consultas de contagem antes/depois em `banco_de_leite_dev` confirmaram preservação de duas contas internas e uma solicitação; interessadas, contatos e concessões permaneceram zerados. As três tabelas novas de agenda estão vazias. Nenhum dado fictício foi inserido no desenvolvimento, nenhuma permissão foi concedida e não houve alteração de `.env` ou dependências.

Reexecução em `banco_de_leite_schema_test`, com `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE` explícito e `DB_URL` vazio: `php vendor/bin/phpunit tests/Feature/AgendaWebTest.php tests/Feature/AgendaConcurrencyTest.php`, **11 testes e 109 assertions aprovados**. Cobre fluxo web e os dois cenários concorrentes existentes, não carga nem todas as combinações de operações. Sem mudança de código, a suíte completa não foi repetida.

Inventário da ferramenta de UI novamente retornou zero navegadores; revisão visual/responsividade e teste manual local continuam pendentes. Admin já possui acesso pelo Gate; funcionário depende da concessão explícita. Próximo recorte: detalhar atendimento/relatório e permissões profissionais antes de implementar, preservando rascunho e versões após finalização já acordados. Não houve commit nesta etapa.

### Aplicação das contas internas — 28/10/2026

Após o usuário reativar o servidor e autorizar nova tentativa, a migration `2026_10_27_202742_restrict_users_to_internal_accounts` foi aplicada isoladamente em `banco_de_leite_dev`, lote 4, usando `migrate --path=database/migrations/2026_10_27_202742_restrict_users_to_internal_accounts.php --no-interaction`. A pendência de conexão registrada em 27/10 foi resolvida.

Executado também `db:seed --class=PermissaoSeeder --no-interaction`, conforme a atualização de catálogo já prevista. Consultas posteriores confirmaram uma conta admin, uma funcionário e nenhuma doadora; enum `admin|funcionario`, obrigatório e sem padrão; três códigos no catálogo e zero concessões a funcionários. Nenhuma conta interna foi recriada e não houve `migrate:fresh` ou `DatabaseSeeder`.

`migrate:status` confirmou a aplicação e as quatro migrations da agenda ainda pendentes. `db:table users` não concluiu por falta da extensão PHP `intl`; a conferência de contas/coluna/catálogo foi feita por consultas somente de leitura, sem exibir dados pessoais. Sem alteração de código ou repetição da suíte de testes nesta aplicação. As evidências anteriores de testes continuam registradas abaixo. Não houve commit nesta etapa.

### Fluxo web da agenda — 27/10/2026

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

### Verificações das operações de agenda — 26/10/2026

Após autorização para avançar, implementados contatos após admissão e confirmação, reagendamento, cancelamento e registro de não realização. O usuário confirmou o bloqueio de sobreposição do funcionário; horários distintos no mesmo dia continuam permitidos. Regras efetivas em [Contratos](CONTRATOS.md#operações-de-backend-implementadas-em-26102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit` em SQLite `:memory:` | **199 testes: 197 aprovados, 2 ignorados, 858 assertions**. Os dois ignorados exigem MariaDB para concorrência real. |
| `php vendor/bin/phpunit tests/Feature/AgendaOperationsTest.php tests/Feature/AgendaConcurrencyTest.php` no MariaDB exclusivo `banco_de_leite_schema_test` | **24 testes e 117 assertions aprovados**. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado. |

Processos com `APP_ENV=testing`, conexão/banco explicitamente definidos e `DB_URL` vazio, sem alteração do `.env`. A guarda de isolamento precede a preparação do banco. Os testes concorrentes usam dois processos PHP e uma barreira transacional; uma primeira tentativa encontrou timeout na sincronização do teste, corrigido com sinalização por arquivos temporários removidos ao finalizar. A reexecução dos dois casos passou, assim como a execução conjunta acima.

Não foram aplicados migrations/seeders ao desenvolvimento nem alteradas dependências ou dados reais. Não há envio automático de WhatsApp. Limites e cobertura em [E-10](../SEGURANCA.md#e-10--operações-e-concorrência-da-agenda-26102026). Próximo recorte: endpoints e formulários Blade de acompanhamento/agenda, com autorização, erros de validação/conflito e apresentação do histórico. Relatório e etapas clínicas continuam fora desta entrega.
### Verificações de models e autorização da agenda — 26/10/2026

Após autorização, implementados `Agendamento`, `ReservaAgenda` e `EventoAgendamento`, factories, relações com solicitações/contatos e Gate `gerenciar-agenda`. O seeder de catálogo e a concessão/revogação auditada agora contemplam atendimento e agenda. Não foram criadas rotas, telas, operações de reserva ou permissões clínicas. Fonte do comportamento em [Contratos](CONTRATOS.md#contrato-técnico-proposto-da-agenda--26102026).

| Verificação executada | Resultado |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/AgendaModelsAuthorizationTest.php tests/Feature/EmployeeAuthorizationTest.php` em SQLite `:memory:` | **35 testes, 148 assertions aprovados**. |
| Mesmo comando em MariaDB local exclusivo `banco_de_leite_schema_test` | **35 testes, 148 assertions aprovados**. |
| `php vendor/bin/phpunit` em SQLite `:memory:` | **175 testes, 757 assertions aprovados**. |
| `php vendor/bin/pint --dirty --format agent` e `git diff --check` | Aprovados. |

Processos com `APP_ENV=testing`, conexão/banco explicitamente definidos e `DB_URL` vazio; classes protegidas pela guarda de banco isolado. A nova classe usa `RefreshDatabase`. Testes cobrem relações/casts, proteção de atribuição em massa, reserva sem ID incremental, histórico preservado após liberar reserva, autorização independente/acumulada, catálogo idempotente, revogação e autoria. O endpoint usado para testar middleware foi registrado somente dentro do teste.

Nenhuma migration/seeder ou concessão executada no desenvolvimento; nenhuma alteração de `.env`, dependências ou dados reais. Sem teste de navegador, concorrência entre conexões ou operação real de agenda. Próximo recorte: contatos após admissão e confirmação/reagendamento/cancelamento transacionais, seguidos de telas, com os limites operacionais registrados em Contratos. Evidência de segurança: [E-09](../SEGURANCA.md#e-09--models-e-autorização-da-agenda-26102026).

### Verificações do schema de agenda — 26/10/2026

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

### Verificações de atendimento administrativo — 26/10/2026

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

### Ativação local — 24/10/2026

Após autorização do usuário, foram executados `php artisan migrate --no-interaction` e `php artisan db:seed --class=PermissaoSeeder --no-interaction` em `banco_de_leite_dev`. As seis migrations administrativas foram aplicadas no lote 3; `migrate:status` confirmou as dez migrations aplicadas. Não houve recriação do banco, execução de `DatabaseSeeder` ou mudança de `.env`.

A consulta final confirmou três contas preservadas, um item no catálogo de permissões, zero concessões a funcionários e zero solicitações. Administradores podem acessar o módulo conforme o Gate existente; funcionários dependem de concessão explícita.

Um servidor temporário conectado exclusivamente a `banco_de_leite_schema_test` retornou HTTP 200 em `/doar` e `/pedido-recebido`, e 302 na fila interna sem autenticação. Não foram inseridos dados por essa verificação. A revisão visual não pôde ser realizada: a ferramenta informou que o navegador não estava disponível e listou zero navegadores. Layout, interação autenticada e responsividade continuam pendentes. Esses checks HTTP não substituem os testes funcionais anteriores nem a homologação visual. Evidência de segurança: [E-06](../SEGURANCA.md#e-06--ativação-local-24102026).

As seções anteriores registram o estado na ocasião de cada verificação; suas referências a migrations pendentes foram superadas por esta ativação.

### Verificações de recebimento e fila — 24/10/2026

- `php vendor/bin/phpunit tests/Feature/SolicitacaoFlowTest.php`: **36 testes, 233 assertions aprovados** no MariaDB 10.4.32 exclusivo `banco_de_leite_schema_test`, com `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test` e `DB_URL` vazio.
- `php vendor/bin/phpunit`: **108 testes, 457 assertions aprovados** em SQLite `:memory:`, com ambiente testing, URL vazia e FKs habilitadas, após os ajustes finais. `php vendor/bin/pint --dirty --format agent`: aprovado.
- Cobertura e limites em [Segurança, E-05](../SEGURANCA.md#e-05--recebimento-fila-e-correspondências-24102026). Sem revisão visual em navegador, teste de carga ou concorrência real. Não houve alteração de dependências, CSS/JS, `.env` ou aplicação de migrations/seeders no banco de desenvolvimento.
- Para uso local das rotas novas e da navegação de funcionários, o schema administrativo precisa estar aplicado e as concessões preparadas por operação autorizada. Esta entrega não fez essa ativação.

### Verificações de models e autorização — 24/10/2026

- Suíte completa em SQLite `:memory:`, com `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vazio e FKs habilitadas: `php vendor/bin/phpunit`, **72 testes e 224 assertions aprovados**. São 27 casos adicionais aos 45 anteriores.
- `php vendor/bin/pint --dirty --format agent`: aprovado. Testes novos em `AdministrativeModelsTest` e `EmployeeAuthorizationTest`; ampliado `ProfileTest`. Relações/casts, matriz de acesso, auditoria transacional e preservação de autoria cobertos; detalhes em [Segurança, E-03](../SEGURANCA.md#e-03--models-permissões-e-exclusão-com-histórico-24102026).
- Tentativa de execução das três classes no MariaDB exclusivo: **32 erros de conexão recusada, zero assertions**, com servidor local indisponível em `127.0.0.1:3306`. Essa primeira tentativa não validou as operações; a reexecução bem-sucedida está registrada abaixo. Concorrência real permanece pendente.
- Sem alteração do `.env`, migrations/seeders no desenvolvimento ou novas dependências. As novas tabelas continuam exigindo aplicação autorizada antes de uso local; as rotas atuais não foram convertidas para o fluxo de pedidos.

#### Reexecução MariaDB de models e autorização — 24/10/2026

Após ativação do servidor e solicitação do usuário, `php vendor/bin/phpunit tests/Feature/AdministrativeModelsTest.php tests/Feature/EmployeeAuthorizationTest.php tests/Feature/ProfileTest.php` passou com **32 testes e 150 assertions** no MariaDB 10.4.32, usando exclusivamente `banco_de_leite_schema_test`. Variáveis de processo: `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test` e `DB_URL` vazio. Sem mudanças de código ou `.env`.

A inspeção final confirmou 20 tabelas, 10 migrations e zero registros de aplicação no banco de testes. A estrutura permanece disponível; os dados fictícios foram revertidos pelas transações dos testes. No banco de desenvolvimento, `migrate:status` confirmou as quatro migrations anteriores aplicadas e seis novas pendentes. Detalhes e limites em [Segurança, E-04](../SEGURANCA.md#e-04--reexecução-dos-models-e-autorização-no-mariadb-24102026). Concorrência real e futuros endpoints continuam fora desta evidência.

### Verificações de 24/10/2026 — schema administrativo

Ambiente: PHP 8.5.10, Laravel 13.34.0, PHPUnit 12.5.37. Os processos de teste receberam explicitamente `APP_ENV=testing`, `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`, `DB_URL` vazio e FKs habilitadas. Não foi alterado `.env`.

| Verificação executada | Resultado e limites |
| --- | --- |
| `php vendor/bin/phpunit tests/Feature/AdministrativeSchemaTest.php` | 20 testes aprovados, 34 assertions. Exercita duplicidade permitida de dados, unicidade das operações/protocolos, FKs, histórico, permissões e rollback/reaplicação do recorte preservando contas preexistentes em memória. |
| `php vendor/bin/pint --dirty --format agent` | Aprovado para os arquivos PHP alterados. |
| `php vendor/bin/phpunit` | 45 testes aprovados, 95 assertions, após formatação. Inclui os 25 testes anteriores; não comprova MySQL, navegador, permissões nas futuras rotas ou concorrência real entre operadores. |

O banco `banco_de_leite_dev` foi consultado apenas para metadados/status. Nenhuma migration/seed foi aplicada nele. A verificação adicional de MariaDB está registrada abaixo; concorrência, consultas de correspondência e comportamento sob volume real continuam pendentes.

#### Integração MariaDB em banco exclusivo — 24/10/2026

Após autorização do usuário, foi criado `banco_de_leite_schema_test`, inicialmente inexistente, no servidor local **MariaDB 10.4.32**, com charset `utf8mb4` e collation `utf8mb4_unicode_ci`. A execução usou a conexão `mysql` do Laravel e variáveis somente no processo: `APP_ENV=testing`, `DB_CONNECTION=mysql`, `DB_DATABASE=banco_de_leite_schema_test`, `DB_URL` vazio. Nenhuma credencial ou configuração de `.env` foi alterada.

- `php vendor/bin/phpunit tests/Feature/AdministrativeSchemaTest.php`: **20 testes aprovados, 34 assertions** no MariaDB. Aplicação das migrations, restrições, preservação de autoria e rollback/reaplicação do recorte foram exercitados com dados fictícios.
- O teste de schema passou a usar `DatabaseMigrations`, pois DDL no MySQL/MariaDB pode realizar commit implícito. A recriação e o rollback automáticos ficam limitados ao banco descartável; uma guarda anterior às migrations aceita somente SQLite em memória ou o banco local com o nome exato acima, em `testing`, sem URL externa nem configuração em cache.
- Após o ajuste, `php vendor/bin/phpunit` em SQLite `:memory:`: **45 testes aprovados, 95 assertions**. `php vendor/bin/pint --dirty --format agent`: aprovado.

O banco de testes foi mantido para futuras execuções; o teardown reverte as tabelas da aplicação. A inspeção final confirmou somente a tabela `migrations`, sem registros. Esta evidência vale para a versão local do MariaDB, não comprova MySQL 8, concorrência entre operadores, performance nem regras de aplicação ainda não implementadas. A consulta final de `migrate:status` no banco de desenvolvimento confirmou as quatro migrations anteriores aplicadas e as seis novas pendentes.

### Levantamento inicial — 22/10/2026

As execuções abaixo ocorreram **anteriormente nesta mesma sessão de preparação local**, em 22/10/2026. Não foram repetidas nesta tarefa documental e não há relatório versionado de CI.

| Estado | Evidência anterior | Limite |
| --- | --- | --- |
| Validado localmente | PHP 8.5.1; suíte retornou 25 testes aprovados e 61 assertions, usando SQLite em memória. | Antes da troca local para MySQL; não comprova matriz de papéis, MySQL ou navegador. |
| Validado localmente | Build Vite 8.3.5 concluído após correções de dependências; Composer validou o manifesto e os requisitos de plataforma naquele momento. | Não é auditoria contínua nem revisão visual. |
| Validado localmente | Após a mudança para MySQL, quatro migrations aplicadas e três contas fictícias listadas em `banco_de_leite_dev.users`. | Ambiente XAMPP local; sem teste automatizado de integração MySQL. |
| Validado localmente | Após a mudança para MySQL, respostas HTTP 200 em início, login e cadastro. | Verificação de resposta, sem interação ou login completo em navegador. |
| Verificado em 22/10/2026 | Leitura das fontes e comparação com os documentos. | Inspeção estática; verificações documentais finais são informadas na entrega. |
