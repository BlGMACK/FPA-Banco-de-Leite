# Modelagem

## Estrutura implementada

Fonte principal: [migrations](../../database/migrations). Esta descrição representa o schema declarado pelo código, não uma inspeção atual de cada banco de integrante.

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

[User](../../app/Models/User.php) é o único model da aplicação encontrado. Permite atribuição de `name`, `email`, `password` e `role`, oculta senha/token e faz casts de senha e data de verificação. O controller de cadastro não aceita papel enviado pelo formulário; não reutilize entrada inteira de uma requisição para preencher esse model.

Há associação lógica entre sessão e conta por `sessions.user_id`, mas a migration não declara `constrained()` nem chave estrangeira. Tokens de recuperação usam e-mail como identificador, sem FK declarada para `users`. Não foram encontrados métodos próprios de relação Eloquent nem entidades de coleta/estoque.

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

Referências: **G** = guia do usuário de 23/10/2026; **L** = schema legado acima; **P** = nova proposta. O [Roadmap](ROADMAP.md) identifica acordos e decisões D1–D12. Todas as tabelas, tipos, cardinalidades e transições abaixo são **P**, mesmo quando materializam uma diretriz G. Não são migrations aprovadas. **PDF indisponível:** não há campos clínicos ou limites de aptidão atribuídos a ele.

### Escolha recomendada e alternativa — D9

Recomenda-se identificar a pessoa em `interessadas` desde a solicitação e criar `doadoras` somente na primeira aprovação. A doadora referencia a pessoa e a decisão inicial; nome, contato e documentos não são recopiados. Novas avaliações preservam a mesma identidade e a história das decisões.

A alternativa é manter um único cadastro com situação de habilitação, sem tabela `doadoras`. Ela reduz uma entidade, mas exige distinguir explicitamente uma pessoa cadastrada de uma doadora aprovada em todas as consultas. Ambas atendem à separação conceitual G; esta especificação desenvolve a primeira, ainda sujeita à confirmação.

### Relações propostas

| Origem | Relação proposta | Justificativa e origem |
| --- | --- | --- |
| Conta ↔ interessada | Interessada tem zero ou uma conta; conta tem zero ou uma interessada vinculada. | G separa conceitos; P permite conta opcional até D1. Autores da equipe são referências distintas. |
| Interessada → solicitações | Uma pessoa pode ter várias solicitações ao longo do tempo. | G exige histórico; P permite retorno sem apagar recusa. Quantidade simultânea depende de D7. |
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
| `interessadas` | `user_id` FK N/único; `nome` texto até 150 O; `telefone` texto até 20 D; `email_contato` até 255 N; `cpf` texto normalizado D; `cartao_sus` texto D. | Nome/telefone/documentos: L; identidade compartilhada e conta opcional: P. Sugerir nome e pelo menos um contato na entrada; confirmar D1/D3/D7. Não assumir que contato e login sejam iguais. |
| Endereço da interessada | Campos candidatos: município, bairro, logradouro, número, complemento e CEP, como texto; obrigatoriedade D. | Endereço: G/L. Estrutura P; D4 decide conjunto mínimo e momento. Número deve aceitar “s/n”. Não criar dois cadastros de endereço concorrentes. |
| `solicitacoes` | `interessada_id` FK O; `protocolo` texto único gerado O; `data_desejada` data D; `canal_origem` código O; `status` código O; `registrada_por_user_id` FK N; `enviada_em` data/hora O. | Preferência e entrada: G/L; protocolo/canal/autoria: P. Canal web; telefone/presencial somente se D2 for confirmado. Autor nulo só se entrada sem conta for aprovada. |
| `agendamentos` | `solicitacao_id` FK O; `inicio_previsto_em` data/hora O ao confirmar; `modalidade` código D; `local_atendimento` D; `status` código O; `confirmado_por_user_id` FK O; `confirmado_em` data/hora O; `substitui_agendamento_id` FK N. | Data combinada distinta da preferência: G; separação e histórico: P. Modalidades e detalhes de local a confirmar. |
| `atendimentos` | `agendamento_id` FK único O; `realizado_em` data/hora O; `responsavel_user_id` FK O. | Atendimento: G/L; estrutura P. Registrar fato ocorrido, não apenas chegada da data agendada. |
| `triagens` | `atendimento_id` FK O; `versao` inteiro O; `substitui_triagem_id` FK N; `status` código O; `preenchida_por_user_id` FK O; `concluida_em` data/hora N; conteúdo clínico D. | Separação: G/L; versão P. L tinha moradia, renda, intercorrências e dados do bebê; não os tornar obrigatórios nem copiar campos livres sem D3/D6. |
| `avaliacoes_saude` | `triagem_id` FK O; `substitui_avaliacao_id` FK N; `status` código O; `avaliador_user_id` FK O; `concluida_em` data/hora N; `valida_ate` data N/D; parecer técnico restrito D. | G/L: avaliação própria; P: autoria e versões. Documentos, exames, resultados estruturados e validade dependem de D6. Nulo não significa validade ilimitada. |
| `decisoes` | `avaliacao_saude_id` FK única O; `resultado` código O; `decidida_por_user_id` FK O; `decidida_em` data/hora O; `justificativa_restrita` texto D; `mensagem_interessada` texto N/D. | Autoria/data/fundamento e proteção da justificativa: G; estrutura P. Sugerir justificativa obrigatória para resultado desfavorável. Conteúdo exibido depende de D11. |
| `doadoras` | `interessada_id` FK única O; `decisao_inicial_id` FK única O. | Criação na aprovação: P/D9. Instante da habilitação inicial vem da decisão; não duplicar nome, endereço, CPF ou sorologia. |
| `eventos_solicitacao` | `solicitacao_id` FK O; tipo de evento O; estados anterior/novo N; `autor_user_id` FK N; `ocorrido_em` data/hora O; vínculo com registro afetado e motivo conforme ação. | Histórico: G; armazenamento P. FKs do registro afetado a detalhar por tipo; evitar ID sem integridade referencial. Dados de saúde não entram em log geral. |

Dados de identificação são mantidos em uma fonte compartilhada. Correções devem gerar histórico restrito dos valores alterados, suficiente para recuperar o contexto de um atendimento/decisão anterior. O desenho físico desse histórico e do endereço fica pendente de D4/D10; não substituir isso por cópias independentes em cada etapa.

### Estados e transições propostos

Separar **andamento administrativo**, **andamento da avaliação** e **resultado da decisão**. Valores abaixo são P, não enums já escolhidos para o banco.

| Entidade | Estados propostos | Transições e condições |
| --- | --- | --- |
| Solicitação | `enviada`, `em_analise`, `aguardando_atendimento`, `em_avaliacao`, `concluida`, `recusada`, `cancelada` | Enviada → em análise pela equipe; → aguardando atendimento ao confirmar agenda; → em avaliação ao registrar atendimento; → concluída ao registrar decisão. Recusa administrativa a partir de enviada/em análise com autor, data e motivo; cancelamento depende de D8. |
| Agendamento | `confirmado`, `realizado`, `cancelado`, `substituido`, `nao_compareceu` | Criado confirmado após acordo, nunca a partir apenas da preferência. Confirmado → realizado com atendimento; → substituído ao registrar novo agendamento; cancelamento/ausência exigem registro da equipe e regra D8. |
| Triagem | `em_preenchimento`, `concluida` | Conclusão por pessoa autorizada e campos exigidos completos. Correção após conclusão cria nova versão; não altera a usada em decisão. |
| Avaliação | `pendente`, `em_avaliacao`, `aguardando_complementacao`, `concluida` | Pendente → em avaliação; pode aguardar complementação e retomar; conclusão exige requisitos acordados. Situação concluída não aprova por si só. |
| Decisão | Resultado `aprovada` ou `desfavoravel` | Criada por profissional autorizado após avaliações exigidas concluídas. É registro final; pendência não é resultado desfavorável. Retificação gera nova avaliação/decisão relacionada. |
| Doadora | Sem enum de ciclo de vida nesta proposta inicial. | Existência registra primeira habilitação; suspensão, vencimento e retorno dependem de D6. Não usar existência da linha como autorização perpétua para coletar. |

No legado L: solicitação tinha pendente/confirmada/recusada; visita pendente/realizada/cancelada; ficha pendente/aprovada/reprovada; requisitos misturavam situação e sorologia; doadora também repetia sorologia. A proposta P distribui cada responsabilidade e retira aprovação da simples conclusão de formulário.

Após agendamento cancelado ou ausência, propõe-se manter a solicitação aberta para análise da equipe; o retorno a `em_analise` e um novo agendamento dependem de D8. Reagendamento preserva a preferência original e o agendamento substituído. Não reabrir solicitação recusada/concluída apagando o estado anterior: sugerir novo pedido vinculado à mesma pessoa, condicionado a D6/D7.

### Integridade e decisões que bloqueiam a implementação

- Verificar que triagem, avaliação, decisão e doadora pertencem à mesma cadeia e interessada; não aceitar combinações de IDs enviadas pelo cliente.
- Unicidade proposta: conta vinculada, protocolo, atendimento por agendamento, versão por atendimento, decisão por avaliação e doadora por interessada. Unicidade de CPF depende de D3/D7; telefone e nome não identificam pessoa de forma suficiente.
- Uma decisão final exige avaliação concluída e todas as etapas exigidas pelo serviço satisfeitas. Até D6 ser confirmado, não há regra clínica programável.
- Aprovação, conclusão administrativa e criação da doadora devem ocorrer atomicamente, com proteção contra envio repetido e decisões concorrentes. Desfecho desfavorável não cria doadora.
- Na primeira aprovação, habilitar a participação conforme G; nas futuras coletas, verificar a decisão aplicável e validade/reavaliação a definir. Decisão desfavorável posterior não pode deixar uma autorização antiga valendo por simples existência do cadastro; tratamento depende de D6.
- Propor impedimento de exclusão física de contas com autoria ou vínculos de domínio até política D10 definida. O código atual exclui contas fisicamente: esse comportamento precisará ser revisto antes dos novos vínculos, sem apagar a autoria histórica.
- Preservar recusas, decisões e versões; não usar exclusão em cascata para a cadeia de origem. Correção e retenção devem ter acesso controlado e política definida.
- Vincular conta a interessada somente após comprovação de identidade definida em D1/D7; não vincular automaticamente por nome, CPF ou e-mail informado.
- Índices candidatos: protocolo, identidade validada e filas por estado/data. Desenho físico e restrições de simultaneidade serão revisados antes das migrations, após confirmar regras e consultas.

A matriz de ações e acesso está em [Contratos](CONTRATOS.md#permissões-propostas-para-o-fluxo-de-admissão). Nada nesta proposta altera o schema atual ou define critérios clínicos.
