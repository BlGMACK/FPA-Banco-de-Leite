# Modelagem

## Estrutura implementada

Fonte principal: [migrations](../../database/migrations). Esta descrição representa o schema declarado pelo código, não uma inspeção atual de cada banco de integrante.

Em **24/10/2026**, foram preparadas seis migrations do recorte administrativo e verificadas em SQLite em memória e, posteriormente, em MariaDB 10.4.32 em banco exclusivo para testes. Após autorização posterior do usuário, foram aplicadas ao banco `banco_de_leite_dev`, preservando as contas existentes; veja [Status](STATUS.md#ativação-local--24102026). O schema físico novo está em [Recorte administrativo preparado](#recorte-administrativo-preparado-em-24102026); a tabela abaixo descreve a base anterior.

| Tabela | Campos e restrições relevantes | Origem |
| --- | --- | --- |
| `users` | `id` PK; `name`; `email` único; `email_verified_at` anulável; `password`; `remember_token` anulável; timestamps. | [migration inicial](../../database/migrations/0001_01_01_000000_create_users_table.php) |
| `users.role` | Enum `admin`, `funcionario`, `doadora`; padrão `doadora`. | [migration de papel](../../database/migrations/2026_10_04_000001_add_role_to_users_table.php) |
| `password_reset_tokens` | `email` PK, `token`, `created_at` anulável. | [migration inicial](../../database/migrations/0001_01_01_000000_create_users_table.php) |
| `sessions` | `id` PK; `user_id` anulável/indexado; `ip_address` e `user_agent` anuláveis; `payload`; `last_activity` indexado. | [migration inicial](../../database/migrations/0001_01_01_000000_create_users_table.php) |
| `cache` | `key` PK; `value`; `expiration` indexado. | [migration de cache](../../database/migrations/0001_01_01_000001_create_cache_table.php) |
| `cache_locks` | `key` PK; `owner`; `expiration` indexado. | [migration de cache](../../database/migrations/0001_01_01_000001_create_cache_table.php) |
| `jobs` | `id` PK; `queue` indexado; `payload`, `attempts`, `reserved_at` anulável, `available_at`, `created_at`. | [migration de filas](../../database/migrations/0001_01_01_000002_create_jobs_table.php) |
| `job_batches` | `id` PK; `name`; contagens `total_jobs`, `pending_jobs`, `failed_jobs`; `failed_job_ids`; `options`, `cancelled_at`, `finished_at` anuláveis; `created_at`. | [migration de filas](../../database/migrations/0001_01_01_000002_create_jobs_table.php) |
| `failed_jobs` | `id` PK; `uuid` único; `connection`, `queue`, `payload`, `exception`, `failed_at` com horário atual por padrão; índice composto de conexão/fila/data. | [migration de filas](../../database/migrations/0001_01_01_000002_create_jobs_table.php) |

O Laravel também mantém `migrations` como controle das migrações aplicadas; não é entidade do domínio.

## Model e relações

[User](../../app/Models/User.php) permite atribuição em massa somente de `name`, `email` e `password`; `role` foi retirado dessa lista. Oculta senha/token e faz casts de senha e data de verificação. Possui relações com concessões e eventos de permissão e consulta o estado atual da conta para os Gates administrativos.

Há associação lógica entre sessão e conta por `sessions.user_id`, mas a migration não declara `constrained()` nem chave estrangeira. Tokens de recuperação usam e-mail como identificador, sem FK declarada para `users`. Não há entidades de coleta/estoque.

Em 24/10/2026 foram implementados `Interessada`, `Solicitacao`, `ContatoSolicitacao`, `ResolucaoSolicitacao`, `EventoSolicitacao`, `Permissao`, `ConcessaoPermissao` e `EventoPermissao`, em [Models](../../app/Models), com factories fictícias. As três associações com chave composta usam relações `belongsToMany`, sem model com chave primária composta. Metadados JSON dessas associações permanecem no pivot, sem cast próprio.

Os models usam tabelas/chaves explícitas, casts de JSON, booleanos, versões e datas imutáveis; registros sem `updated_at` declaram `UPDATED_AT = null`. Novos models mantêm a proteção padrão contra atribuição em massa de todos os campos. A futura camada de escrita deve validar e atribuir explicitamente os valores; factories não representam validação de dados de negócio. Relações/casts não executam admissão, triagem, validação clínica ou controle de acesso automaticamente.

Conta com papel `doadora` representa autorização, não um cadastro de doadora com dados de saúde. Não há tabela de domínio para esse cadastro.

## Dados e ambiente

A [factory](../../database/factories/UserFactory.php) cria usuários fictícios e oferece estado sem verificação de e-mail. O [seeder](../../database/seeders/DatabaseSeeder.php) cria uma conta por papel, com valores fixos de demonstração; não é idempotente.

Conexão local, migrations e diferença entre MySQL e SQLite de testes: [Ambiente](../AMBIENTE.md). O levantamento original de 22/10/2026 não inspecionou o legado; a consulta posterior está registrada abaixo e não comprova compatibilidade.

## Referência do legado consultada em 23/10/2026

Foi lido, sem executá-lo, o `schema.sql` da branch **local** `Banco-de-leite_old`, no commit `20e39205bdf6217baaa07ec89d3dc9f7c9ea4222`. A consulta não verifica atualização remota, banco em uso, dados reais nem conformidade com o processo atual do serviço.

Para discutir o primeiro fluxo, o schema mostra `SOLICITACAO → VISITA → FICHA_TRIAGEM → REQUISITOS_SAUDE`, além de `DOADORA`, cujo vínculo com requisitos de saúde é anulável e único. Isso permite representar no schema uma doadora sem esse vínculo; não comprova que o serviço aceite esse caminho operacionalmente.

`DOADORA` contém nome e CPF obrigatórios, CNS, telefone e endereço opcionais e um estado de sorologia. O CPF não tem restrição `UNIQUE` declarada. Não há tabela de contas nesse schema nem vínculo com `users`. Esses campos são referências históricas, não um dicionário de dados aprovado para Laravel.

Antes de reutilizar o desenho, confirmar a identificação da pessoa, a regra de duplicidade, o momento do cadastro e a separação entre identificação e avaliação de aptidão. O usuário informou posteriormente que não há PDF ou artefato detalhado e forneceu um guia textual. Suas diretrizes e pendências estão em [R1 no Roadmap](ROADMAP.md#r1--solicitação-até-habilitação-para-doar). Nenhuma regra de PDF foi analisada.

## Proposta do fluxo de admissão — não implementada

Referências: **G** = guia do usuário de 23/10/2026; **L** = schema legado acima; **P** = nova proposta. O [Roadmap](ROADMAP.md) identifica acordos e decisões D1–D12. O desenho completo abaixo continua **P**, mesmo quando materializa uma diretriz G. A autorização posterior de 24/10/2026 permitiu preparar apenas o schema administrativo descrito ao final; operações e tabelas clínicas continuam propostas. **PDF indisponível:** não há campos clínicos ou limites de aptidão atribuídos a ele.

### Escolha recomendada e alternativa — D9

Recomenda-se confirmar a identificação da pessoa em `interessadas` na conferência do pedido e criar `doadoras` somente na primeira aprovação. A doadora referencia a pessoa e a decisão inicial; nome, contato e documentos não são recopiados. Novas avaliações preservam a mesma identidade e a história das decisões.

A alternativa é manter um único cadastro com situação de habilitação, sem tabela `doadoras`. Ela reduz uma entidade, mas exige distinguir explicitamente uma pessoa cadastrada de uma doadora aprovada em todas as consultas. Ambas atendem à separação conceitual G; esta especificação desenvolve a primeira, ainda sujeita à confirmação.

### Relações propostas

| Origem | Relação proposta | Justificativa e origem |
| --- | --- | --- |
| Conta ↔ interessada | Na hipótese de entrada sem conta, não exigir nem criar vínculo de login da interessada. | Preferência posterior do usuário em D1; contas da equipe continuam sendo referências de autoria. Vínculo futuro depende de decisão sobre portal. |
| Interessada → solicitações | Pessoa confirmada pode ter vários pedidos, inclusive simultâneos sujeitos à análise. Pedido recebido pode estar sem vínculo até conferência. | G revisado: sinalizar correspondências, não bloquear por repetição; P: vínculo confirmado pela equipe. |
| Solicitação → agendamentos | Zero ou vários registros; no máximo um confirmado ativo por vez na proposta. | L ligava visita à solicitação; P separa agenda de execução e preserva reagendamentos. |
| Agendamento → atendimento | Zero ou um atendimento realizado. | P; ausência ou cancelamento não criam atendimento fictício. Encaixe sem agenda depende de D8. |
| Atendimento → triagens | Zero ou várias versões, identificadas por número e antecessora. | G separa triagem; L tinha uma ficha por visita. P mantém versões para preservar decisões. |
| Triagem → avaliações de saúde | Zero ou várias avaliações, cada uma vinculada à versão exata da triagem. | G mantém avaliação separada; L tinha um requisito por ficha. P preserva complementações/reavaliações. |
| Avaliação → decisão | Zero ou uma decisão final por avaliação. | G exige fundamento; P impede dupla finalização. Retificação exige nova avaliação vinculada à anterior. |
| Interessada → doadora | Zero ou uma; criada apenas com decisão aprovada. | G exige aprovação prévia; P escolhe criação na primeira aprovação. |
| Doadora → decisão inicial | Uma decisão aprovada, pertencente à mesma interessada. | G rastreabilidade; P mantém referência estável da primeira habilitação. |
| Solicitação → eventos | Vários eventos de estado/alteração, com autor e instante. | G exige histórico; P detalha registro de ações. |

A cadeia de origem é: doadora → decisão inicial → avaliação → versão da triagem → atendimento → agendamento → solicitação → interessada. Outras decisões são recuperadas pela cadeia da mesma pessoa, sem sobrescrever a decisão inicial.

### Campos propostos

Notação: **O** = obrigatório na proposta; **N** = anulável; **D** = obrigação/conteúdo a decidir. Para todas as entidades novas: `id` inteiro grande gerado no servidor e timestamps. FKs usam o mesmo tipo dos IDs referenciados. Nomes e limites são rascunho técnico, não contrato já aprovado.

| Entidade | Campos e tipos propostos | Origem e pendências |
| --- | --- | --- |
| `users` existente | Manter nome, e-mail, senha e papel atuais. | Implementado. G: papel não representa aptidão. Não adicionar aprovação clínica ao papel. |
| `interessadas` | Nome completo, CPF, e-mail, fixo/celular, endereço e indicação de WhatsApp conforme dicionário abaixo; CNS fora do formulário inicial. | Dados básicos definidos pelo usuário; formatos/limites P. Cadastro compartilhado somente após conferência; não confundir com dados declarados de novo envio. |
| Endereço da interessada | Município, bairro e endereço completo obrigatórios; decomposição proposta abaixo. | Obrigatoriedade confirmada pelo usuário; formatos P. Aceitar número “s/n” e complemento opcional. |
| `solicitacoes` | `interessada_id` FK N até conferência; `protocolo` único O; dados declarados conforme dicionário; `canal_origem`, `status`, `enviada_em` O; `registrada_por_user_id` FK N para envio público e O para interno. | Todo pedido recebido fica na fila; ligação com pessoa existente só após conferência. Sem unicidade de CPF/e-mail/telefone nos pedidos. Data desejada e CNS permanecem fora do conjunto obrigatório inicial. |
| `agendamentos` | `solicitacao_id` FK O; `inicio_previsto_em` data/hora O ao confirmar; `modalidade` código D; `local_atendimento` D; `status` código O; `confirmado_por_user_id` FK O; `confirmado_em` data/hora O; `substitui_agendamento_id` FK N. | Data combinada distinta da preferência: G; separação e histórico: P. Modalidades e detalhes de local a confirmar. |
| `atendimentos` | `agendamento_id` FK único O; `realizado_em` data/hora O; `responsavel_user_id` FK O. | Atendimento: G/L; estrutura P. Registrar fato ocorrido, não apenas chegada da data agendada. |
| `triagens` | `atendimento_id` FK O; `versao` inteiro O; `substitui_triagem_id` FK N; `status` código O; `preenchida_por_user_id` FK O; `concluida_em` data/hora N; `relatorio_visita` texto amplo. | Preferência atual do usuário: relatório narrativo, sem questionário clínico estruturado nesta fase. Formato pode mudar; obrigatoriedade de campos clínicos e conclusão profissional dependem de D6. Metadados de autoria/vínculo permanecem estruturados. |
| `avaliacoes_saude` | `triagem_id` FK O; `substitui_avaliacao_id` FK N; `status` código O; `avaliador_user_id` FK O; `concluida_em` data/hora N; `valida_ate` data N/D; parecer técnico restrito D. | G/L: avaliação própria; P: autoria e versões. Documentos, exames, resultados estruturados e validade dependem de D6. Nulo não significa validade ilimitada. |
| `decisoes` | `avaliacao_saude_id` FK única O; `resultado` código O; `decidida_por_user_id` FK O; `decidida_em` data/hora O; `justificativa_restrita` texto D; `mensagem_interessada` texto N/D. | Autoria/data/fundamento e proteção da justificativa: G; estrutura P. Sugerir justificativa obrigatória para resultado desfavorável. Conteúdo exibido depende de D11. |
| `doadoras` | `interessada_id` FK única O; `decisao_inicial_id` FK única O. | Criação na aprovação: P/D9. Instante da habilitação inicial vem da decisão; não duplicar nome, endereço, CPF ou sorologia. |
| `eventos_solicitacao` | `solicitacao_id` FK O; tipo de evento O; estados anterior/novo N; `autor_user_id` FK N; `ocorrido_em` data/hora O; vínculo com registro afetado e motivo conforme ação. | Histórico: G; armazenamento P. FKs do registro afetado a detalhar por tipo; evitar ID sem integridade referencial. Dados de saúde não entram em log geral. |

Dados de identificação são mantidos em uma fonte compartilhada. Correções devem gerar histórico restrito dos valores alterados, suficiente para recuperar o contexto de um atendimento/decisão anterior. O desenho físico desse histórico fica pendente de D10; não substituir isso por cópias independentes em cada etapa.

### Estados e transições propostos

Separar **andamento administrativo**, **andamento da avaliação** e **resultado da decisão**. Valores abaixo são P, não enums já escolhidos para o banco.

| Entidade | Estados propostos | Transições e condições |
| --- | --- | --- |
| Solicitação | `enviada`, `em_analise`, `admitida`, `aguardando_atendimento`, `em_avaliacao`, `concluida`, `recusada`, `cancelada`, `arquivada` | Em análise → admitida representa “cadastro concluído” administrativo, após conferência; → aguardando atendimento somente ao confirmar agenda; → em avaliação ao registrar atendimento; → concluída ao registrar decisão final. Arquivamento após contato segue regra abaixo; cancelamento depende de D8. |
| Agendamento | `confirmado`, `realizado`, `cancelado`, `substituido`, `nao_compareceu` | Criado confirmado após acordo, nunca a partir apenas da preferência. Confirmado → realizado com atendimento; → substituído ao registrar novo agendamento; cancelamento/ausência exigem registro da equipe e regra D8. |
| Triagem | `em_preenchimento`, `concluida` | Conclusão por pessoa autorizada e campos exigidos completos. Correção após conclusão cria nova versão; não altera a usada em decisão. |
| Avaliação | `pendente`, `em_avaliacao`, `aguardando_complementacao`, `concluida` | Pendente → em avaliação; pode aguardar complementação e retomar; conclusão exige requisitos acordados. Situação concluída não aprova por si só. |
| Decisão | Resultado `aprovada` ou `desfavoravel` | Criada por profissional autorizado após avaliações exigidas concluídas. É registro final; pendência não é resultado desfavorável. Retificação gera nova avaliação/decisão relacionada. |
| Doadora | Sem enum de ciclo de vida nesta proposta inicial. | Existência registra primeira habilitação; suspensão, vencimento e retorno dependem de D6. Não usar existência da linha como autorização perpétua para coletar. |

No legado L: solicitação tinha pendente/confirmada/recusada; visita pendente/realizada/cancelada; ficha pendente/aprovada/reprovada; requisitos misturavam situação e sorologia; doadora também repetia sorologia. A proposta P distribui cada responsabilidade e retira aprovação da simples conclusão de formulário.

Após agendamento cancelado ou ausência, preservar a admissão administrativa e propor retorno à espera de novo agendamento; o estado técnico e as regras dependem de D8. Reagendamento preserva a preferência original e o agendamento substituído. Não reabrir solicitação recusada/concluída apagando o estado anterior: sugerir novo pedido vinculado à mesma pessoa, condicionado a D6/D7.

### Integridade e decisões que bloqueiam a implementação

- Verificar que triagem, avaliação, decisão e doadora pertencem à mesma cadeia e interessada; não aceitar combinações de IDs enviadas pelo cliente.
- Unicidade proposta: protocolo, atendimento por agendamento, versão por atendimento, decisão por avaliação e doadora por interessada. Nos pedidos, CPF/e-mail/telefone/nome repetidos geram alerta, não restrição de unicidade. Unicidade no cadastro de identidade confirmada ainda depende da resolução D7; telefone e nome não identificam pessoa de forma suficiente.
- Uma decisão final exige avaliação concluída e todas as etapas exigidas pelo serviço satisfeitas. Até D6 ser confirmado, não há regra clínica programável.
- Na decisão clínica final, aprovação, encerramento dessa etapa e criação da doadora devem ocorrer atomicamente, com proteção contra envio repetido e decisões concorrentes. Concluir a conferência de dados ou resolver repetição não cria doadora aprovada. Desfecho desfavorável não cria doadora.
- Na primeira aprovação, habilitar a participação conforme G; nas futuras coletas, verificar a decisão aplicável e validade/reavaliação a definir. Decisão desfavorável posterior não pode deixar uma autorização antiga valendo por simples existência do cadastro; tratamento depende de D6.
- Propor impedimento de exclusão física de contas com autoria ou vínculos de domínio até política D10 definida. O código atual exclui contas fisicamente: esse comportamento precisará ser revisto antes dos novos vínculos, sem apagar a autoria histórica.
- Preservar recusas, decisões e versões; não usar exclusão em cascata para a cadeia de origem. Correção e retenção devem ter acesso controlado e política definida.
- Na hipótese atual, não criar login ou vínculo de conta da interessada. Se houver portal futuro, definir comprovação de identidade antes de vincular; não vincular automaticamente por nome, CPF ou e-mail informado.
- Índices candidatos: protocolo, identidade validada e filas por estado/data. Desenho físico e restrições de simultaneidade serão revisados antes das migrations, após confirmar regras e consultas.

A matriz de ações e acesso está em [Contratos](CONTRATOS.md#permissões-propostas-para-o-fluxo-de-admissão). Nada nesta proposta altera o schema atual ou define critérios clínicos.

### Entrada pública, duplicidade e contato — proposta revisada

A preferência sem conta em D1 altera a proposta anterior: `interessadas` não precisa de `user_id` neste recorte. `users` permanece para a equipe e para a autenticação já implementada; remover/desativar o cadastro público Breeze e definir o destino das contas doadora existentes será tarefa futura explícita, sem apagar registros nesta etapa.

A regra revisada pelo usuário substitui “uma solicitação ativa por pessoa” por recebimento e sinalização para análise. Não impor unicidade de CPF, telefone, e-mail ou nome em `solicitacoes`. Limitar abuso/automação é uma regra separada; correspondência de dados não equivale a spam.

Proposta de persistência: cada pedido mantém os dados declarados no envio e um vínculo inicialmente anulável à interessada confirmada. Esses dados são a evidência de entrada, não um segundo cadastro mestre. Após conferência, a equipe vincula à pessoa existente ou cria sua identificação; atualizações da identidade exigem ação explícita. Preservar valores originalmente declarados e histórico de correções. Essa estrutura substitui a fila adicional `entradas_solicitacao` anteriormente sugerida.

Pedido sem identidade conferida pode ser recebido/analisado/contatado; antes de agendamento e etapas técnicas, propor vínculo confirmado. Nunca associar dados clínicos automaticamente ao cadastro encontrado por CPF ou telefone.

### Dicionário da abertura do pedido

Obrigatoriedade funcional abaixo confirmada pelo usuário. Nomes técnicos, tamanhos e normalização são **P**. Validar tanto no formulário público quanto no registro interno.

| Campo | Obrigatoriedade | Proposta técnica |
| --- | --- | --- |
| Nome completo | Sim | `nome_completo`, texto até 150; aparar espaços e preservar grafia original. |
| CPF | Sim | Texto de 11 dígitos após remover máscara, com validação dos dígitos verificadores; não prova identidade. |
| Telefone fixo | Pelo menos fixo ou celular | `telefone_fixo`, texto até 20, com DDD; anulável quando celular preenchido. |
| Celular | Pelo menos fixo ou celular | `telefone_celular`, texto até 20, com DDD; anulável quando fixo preenchido. Ambos podem ser informados. |
| E-mail | Sim | `email_contato`, texto até 255, formato válido; não é login nem prova de titularidade. |
| Município e bairro | Ambos | Textos até 150 cada. |
| Endereço completo | Sim | Propor UF (2), CEP normalizado (8), logradouro (255), número (20, aceitando “s/n”) e complemento opcional (150), além de município/bairro. Detalhamento técnico para revisão. |
| Número para WhatsApp | Sim | Seletor fixo/celular entre os campos preenchidos; não repetir telefone em um terceiro campo independente. Escolha confirmada pelo usuário. |
| Disponibilidade no WhatsApp | Sim | Declaração explícita de disponibilidade no número selecionado; registrar momento. Não chamar essa declaração de número verificado. |
| Outros detalhes | Não | Área de texto até 2.000 caracteres, sem HTML ou anexos; limite P. Orientar a não enviar exames ou dados de terceiros. |

Preferência de data estava no guia anterior, mas não na lista final de campos iniciais; manter pendente sua inclusão opcional e tratar o horário combinado no agendamento. CNS, exames e resultados não integram esta abertura.

### Correspondências e alerta único — regra funcional e proposta técnica

**Confirmado pelo usuário:** flag única por pedido, indicando quais dados repetem e em quais pedidos/cadastros já completos aparecem, com filtro de pedidos sinalizados.

**Proposta técnica:** calcular `tem_correspondencias` como existência de pelo menos uma correspondência; detalhes são uma coleção de campo, tipo de destino (pedido ou cadastro confirmado), ID do destino e valor coincidente. Um badge agrega múltiplos motivos/destinos. Contagem de pedidos sinalizados não deve aumentar por número de motivos.

| Campo comparado | Regra inicial proposta | Limite |
| --- | --- | --- |
| CPF | Igualdade após remover máscara. | Indício forte, mas pode haver erro ou uso de dados de terceiro. |
| Telefones | Comparar ambos os campos com ambos nos destinos, após normalizar DDD/número; remover código +55 somente quando identificável. | Fixo e celular podem representar o mesmo contato; número compartilhado não comprova duplicidade. |
| E-mail | Aparar espaços e comparar sem diferença de caixa. | Não remover pontos/sufixos “+”; não inferir que endereços diferentes pertencem à mesma pessoa. |
| Nome completo | Igualdade após normalizar caixa, acentos e espaços para comparação. | Homônimos geram alerta, não bloqueio. Busca aproximada/fonética não integra a primeira proposta. |

Endereço compartilhado não gera alerta sozinho nesta versão proposta; inclusão de outros campos precisa de revisão para não sinalizar toda família ou domicílio como duplicidade. Campos vazios não coincidem entre si.

Comparar somente pedidos ativos e cadastros administrativamente concluídos, conforme esclarecimento do usuário. Proposta de mapeamento: pedidos `enviada/em_analise` participam como pedidos; a partir de `admitida`, comparar a identidade confirmada como cadastro, inclusive quando já houver aprovação final. Não contar o pedido de origem e seu cadastro como dois destinos independentes. Arquivados ficam fora dos dois lados da comparação e não geram flag. Propor excluir pedidos recusados/cancelados como alvos inativos; essa extensão permanece a validar. Excluir o próprio registro e mostrar seu vínculo canônico como “cadastro vinculado”, não como repetição.

Recalcular correspondências quando pedidos/cadastros surgirem, forem corrigidos, admitidos ou arquivados, inclusive nos pedidos antigos afetados. Definir estratégia de consulta/índices na implementação; flags armazenadas não podem ficar desatualizadas.

A análise não apaga pedidos. Propor registrar responsável, data, motivo e encaminhamento (mesma pessoa, pessoas distintas, aguardando esclarecimento). Manter a evidência da coincidência e distinguir alerta pendente de já revisado. Nova correspondência deve exigir nova conferência; nunca realizar fusão automática.

**Definição do usuário:** confirmar que os pedidos são os mesmos exige contato efetivo que esclareça a dúvida. Após essa análise, é permitido arquivar ambos com justificativa, retirando-os da fila de análise. Propor estado `arquivada` para esse desfecho administrativo; não converter em `recusada` ou em resultado clínico desfavorável.

**Persistência proposta:** cada resolução identifica os pedidos efetivamente analisados, funcionário, data/hora, motivo, resultado da conferência e contato que a fundamentou. O contato deve referenciar os pedidos abrangidos pela conversa. Status de contato `realizado`, sozinho, não basta: registrar também que a identidade e a repetição foram esclarecidas, sem copiar a conversa inteira.

Arquivar os dois deve ser uma operação atômica, conferindo autorização e estado atual de cada pedido. Se algum já mudou de estado, solicitar nova análise; não arquivar parcialmente ou alterar cadastro aprovado por associação. Arquivados não aparecem na fila ativa, no filtro de flags atuais nem como destinos de correspondência; continuam no histórico/todos. A evidência da resolução fica no histórico, separada do indicador atual. Se o arquivado era o único destino de uma flag, ela desaparece também no outro pedido.

O usuário confirmou que “cadastro concluído” é admissão administrativa e que o funcionário escolhe qual pedido avança e qual arquiva. Proposta: registrar `admitida` no principal, `arquivada` no repetido e vínculo de resolução com contato/motivo/autor, de forma atômica. Não reutilizar `concluida`, reservado à decisão final. Reabertura permanece a definir.

### Registro de contatos

Contato manual: propor `contatos_solicitacao` ligado à solicitação, com canal, funcionário, data/hora, finalidade, resultado (tentado/realizado/sem_resposta/numero_incorreto), resumo operacional mínimo e próxima ação. Não copiar conversas inteiras ou exames para esse histórico. Registrar como o destinatário foi conferido conforme procedimento a definir; controle de um número não prova, sozinho, a identidade civil.

O telefone informado no formulário não deve substituir automaticamente um contato previamente confirmado. Correções recebidas publicamente ficam pendentes de conferência. Mensagem enviada, recebimento de resposta e confirmação de agendamento são eventos diferentes.

### Permissões configuráveis — proposta de estrutura

Manter contas individuais de funcionários. Propor um catálogo de permissões por ação e vínculo entre conta e permissões concedidas; registrar quem concedeu/revogou, quando e o escopo de registros. Para este recorte, agrupar consulta/cadastro/conferência de pedidos e registro de contato na função “Atendimento de solicitações”, conforme o usuário, atribuível a vários funcionários. Isso é um conjunto de permissões, não obrigação de alterar o enum atual nem de criar contas compartilhadas. Outras funções podem continuar configuráveis. A representação física (tabelas próprias ou recursos existentes) será escolhida em tarefa autorizada; nenhum pacote foi escolhido.

Administrador amplo é proposta do usuário. Seu alcance em atos clínicos ainda exige definição em D5: acesso técnico não elimina requisitos da avaliação, autoria nem critérios profissionais. Mesmo administrador não apaga histórico ou aprova com avaliação incompleta. Catálogo funcional inicial em [Contratos](CONTRATOS.md#permissões-propostas-para-o-fluxo-de-admissão).

### Acompanhamento após admissão administrativa

**Definido pelo usuário:** nova área para os cadastros admitidos, com visita e informações posteriores pendentes; contato para agendamento, visita presencial, relatório, análise e só então solicitação de documentos/exames quando indicada. Admissão administrativa não cria registro de doadora aprovada nem autoriza coleta.

**Proposta técnica:** a área é uma visão do mesmo cadastro/solicitação, não uma cópia de identificação. Registrar `admitida_por_user_id` e `admitida_em` no pedido principal. Manter pendências de visita, análise do relatório e requisitos separados da aprovação. Antes da indicação profissional, documentos/exames ficam “a definir após visita”, não “em atraso” ou “exigidos”.

O relatório narrativo integra a triagem já proposta, com autor, atendimento, versão e data de conclusão. Sua análise registra profissional, instante e encaminhamento: solicitar complementação, indicar documentos/exames ou registrar desfecho fundamentado conforme atribuições D5/D6. Não exigir exames automaticamente para toda pessoa admitida; critérios clínicos continuam pendentes.

Para os requisitos efetivamente indicados, propor itens vinculados à avaliação: tipo/descrição, profissional solicitante, instante, fundamento no relatório e estado `solicitado/recebido/em_analise/aceito/complementacao_necessaria`. Tipos de documentos, exames, prazos e critérios de aceite serão definidos pelo serviço. Arquivo recebido não satisfaz automaticamente o requisito.

Anexos enviados pelo canal de contato são carregados pelo funcionário autorizado e vinculados ao cadastro, avaliação e item solicitado. Propor metadados de tipo, referência privada ao arquivo, nome original, tamanho, tipo de conteúdo, data de recebimento, canal e funcionário responsável. Preservar versões/substituições sem sobrescrever a evidência usada por decisão. Armazenamento privado e autorização para leitura/download; formato, tamanho máximo, retenção e procedimento de recebimento ainda serão definidos.

O conjunto de atendimento pode conduzir o pedido e contato sem obter automaticamente permissão para acessar relatórios ou anexos clínicos. Definir em D5 quem anexa e quem analisa. A inclusão dessa etapa na documentação não autoriza implementar upload, integrações ou regras clínicas.

## Recorte administrativo preparado em 24/10/2026

**Autorizado pelo usuário:** consolidar o schema, preparar migrations e verificar em banco isolado, sem aplicar ao `banco_de_leite_dev`. **Implementado em arquivos:** seis migrations, onze tabelas novas; nenhuma migration antiga foi editada. **Validado:** integridade e rollback/reaplicação em SQLite `:memory:`; não equivale a validação MySQL nem a módulo operacional.

| Migration | Tabelas e finalidade |
| --- | --- |
| [Identificação conferida](../../database/migrations/2026_10_24_045949_create_interessadas_table.php) | `interessadas`: dados de identificação/contato/endereço, declaração de WhatsApp, responsável e instante da conferência; versão para futura concorrência. Sem conta de login. |
| [Pedidos](../../database/migrations/2026_10_24_045952_create_solicitacoes_table.php) | `solicitacoes`: dados declarados e snapshot original, vínculo opcional à interessada, origem, autoria interna, protocolo e operação únicos, hash da entrada, estado, versão, datas de envio/admissão/arquivamento. |
| [Contatos](../../database/migrations/2026_10_24_045955_create_contatos_solicitacao_tables.php) | `contatos_solicitacao` e `contato_pedidos`: contato/tentativa e associação aos pedidos abordados; um contato pode esclarecer vários pedidos. |
| [Conferências e resoluções](../../database/migrations/2026_10_24_045958_create_resolucoes_solicitacao_tables.php) | `resolucoes_solicitacao`, `resolucao_pedidos`, `resolucao_cadastros`: resultado da conferência ou resolução, contato de fundamento, funcionário, motivo, principal e alvos tipados por FKs; versões/evidências analisadas. |
| [Histórico dos pedidos](../../database/migrations/2026_10_24_050002_create_eventos_solicitacao_table.php) | `eventos_solicitacao`: recebimento, análise, correção, conferência, admissão ou arquivamento, com autoria, estados e snapshots pertinentes. |
| [Permissões internas](../../database/migrations/2026_10_24_050005_create_permissoes_atendimento_tables.php) | `permissoes`, `permissao_user`, `eventos_permissao`: catálogo, concessão atual/revogada por funcionário e histórico de concessões/revogações. Nenhuma permissão ou conta foi inserida. |

### Decisões físicas deste recorte

- IDs inteiros grandes e FKs compatíveis com `users.id`. Vínculos usam restrição de exclusão para preservar autoria e referências, sem cascata destrutiva. UUIDs de protocolo/operação são únicos; CPF, telefone, e-mail e nome **não** são únicos nos pedidos nem usados como autenticação.
- `solicitacoes.status` admite somente `enviada`, `em_analise`, `admitida` e `arquivada`. Estados de etapas posteriores precisam de nova migration quando forem implementados. A admissão administrativa não cria uma tabela de doadoras aprovadas.
- Campos de identificação seguem o dicionário; `nome_normalizado` e índices por CPF, telefone e e-mail preparam as comparações. Normalização, validação dos dígitos de CPF, ao menos um telefone e seleção/declaração de WhatsApp serão implementadas na aplicação. As migrations não validam esses cruzamentos.
- Índice da fila por estado/criação/ID; histórico por pedido/instante/ID. Chaves compostas impedem repetir o mesmo alvo em contato/resolução. A performance deve ser medida com consultas e volume reais antes de ampliar os índices.
- `operacao_id` e `conteudo_hash` em pedidos/resoluções dão suporte à repetição técnica de operações. Expiração, origem segura da chave, verificação do hash e resposta idempotente ainda exigem lógica; a restrição única sozinha não implementa esse contrato.
- Contato e resolução são associados por FKs, mas o banco não comprova que houve conversa, que o contato abrange todos os alvos ou que o principal pertence ao conjunto. Verificação de permissões, estados/versões, transação atômica e atualização das flags pertencem à próxima implementação.
- Uma tabela de resoluções também registra conferências pendentes/pessoas distintas; `contato_id` é anulável para situações ainda aguardando esclarecimento. Contato efetivo deve ser obrigatório na aplicação antes de admissão ou arquivamento por repetição.
- `permissao_user` tem uma linha por par usuário/permissão, preservando concessão e eventual revogação; eventos registram o histórico. Isso é persistência, não autorização já conectada ao middleware. O catálogo é vazio até uma tarefa explícita de provisão.
- Histórico e snapshots são dados restritos. A ausência de `updated_at` em registros de evidência não os torna imutáveis no banco: a camada de aplicação deve impedir alterações/exclusões indevidas. Este recorte não implementa correções de identidade canônica, anexos ou auditoria clínica.

### Aplicação, rollback e limites de segurança

As migrations têm `down()` em ordem inversa de dependência. **Rollback elimina as tabelas novas e seus dados**; o teste de reversão usa exclusivamente banco descartável. Não usar rollback como mecanismo de desfazer uma decisão de atendimento.

Quando existirem registros com autoria, as FKs impedem excluir a conta referenciada. O controller de perfil agora trata essa restrição com erro no bag `userDeletion`, preservando a sessão e os dados. Contas sem vínculos ainda podem ser excluídas com senha atual; o encerramento posterior não atualiza o token da conta já removida. Política de desativação de funcionários permanece pendente.

Teste: [AdministrativeSchemaTest](../../tests/Feature/AdministrativeSchemaTest.php), com verificação de ambiente antes de preparar o banco; exige `testing`, ausência de URL externa/configuração em cache e SQLite `:memory:` ou conexão `mysql` local para o nome exato `banco_de_leite_schema_test`. Usa `DatabaseMigrations` para isolar também o teste de DDL, sem depender de rollback transacional no MySQL/MariaDB. Utiliza queries reais para exercitar as restrições e a factory existente para contas fictícias. Fixtures de schema não representam validação de CPF ou dados de negócio.

**Validação adicional autorizada em 24/10/2026:** os 20 testes de schema também passaram no MariaDB 10.4.32, em banco exclusivo, incluindo rollback/reaplicação. Nenhuma migration foi aplicada ao `banco_de_leite_dev`. Ambiente, resultados e limites em [Status](STATUS.md#integração-mariadb-em-banco-exclusivo--24102026).

Fontes de implementação consultadas: [migrations do Laravel 13](https://laravel.com/docs/13.x/migrations) e [testes de banco](https://laravel.com/docs/13.x/database-testing), além do framework instalado. O MCP Boost não estava disponível na sessão; a estrutura de `users` foi consultada somente para leitura. `db:table` não concluiu a apresentação por ausência de `intl`; a leitura alternativa de metadados confirmou as nove colunas sem consultar registros pessoais.
