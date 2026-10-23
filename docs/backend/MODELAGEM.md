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

Conexão local, migrations e diferença entre MySQL e SQLite de testes: [Ambiente](../AMBIENTE.md). O schema do legado não foi inspecionado nesta tarefa; não se assume compatibilidade.

## Pendências de modelagem — propostas

Antes de criar migrations de domínio, discutir entidades do primeiro fluxo, identificadores, campos obrigatórios, relações/cardinalidades, estados e transições, unidades de medida, unicidade, acesso por registro e trilha de auditoria. Definir exclusão versus retenção e minimização de dados com a equipe.

A exclusão de conta atual é física; efeitos sobre futuros registros e histórico exigem decisão antes de adicionar vínculos. A estratégia de importação do legado e de integridade de sessões também está pendente.

Nenhuma tabela nova, regra clínica ou cardinalidade de domínio está acordada aqui. Veja as dependências no [Roadmap](ROADMAP.md).
