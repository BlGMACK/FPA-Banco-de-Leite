# Ambiente

Documento principal de requisitos, instalação, execução e testes. Consulte [Status](backend/STATUS.md) para as evidências de execução, sem assumir que os comandos abaixo foram rodados nesta tarefa documental.

## Requisitos e fontes

- PHP: o projeto declara `^8.3` em [composer.json](../composer.json), mas a combinação completa do lock deve ser respeitada. O ambiente de desenvolvimento usado em 29/10/2026 tinha PHP 8.5.10. Confirme compatibilidade com `composer check-platform-reqs`; não considere apenas o mínimo do framework.
- Composer 2 e as versões fixadas em [composer.lock](../composer.lock).
- Node.js e npm compatíveis com os campos `engines` de [package-lock.json](../package-lock.json). A versão LTS a padronizar pela equipe está pendente; Node 25.5.0 foi usado na preparação inicial, sem constituir padrão.
- MySQL/MariaDB local, inclusive via XAMPP (MariaDB 10.4.32 nas validações registradas). O exemplo compartilhado usa conexão MySQL.
- PHP com extensões exigidas pelo Composer, incluindo `fileinfo`, `mbstring`, XML/DOM e `pdo_mysql`. Os testes também precisam de `pdo_sqlite`; `zip` facilita a instalação. Sem `intl`, `php artisan db:table` não conclui.
- Git para branches e pull requests.

Na raiz da aplicação, confira:

```powershell
php -v
php --ini
php -m
composer -V
node -v
npm.cmd -v
```

## Primeira instalação

Execute na pasta que contém `artisan`. Os exemplos usam PowerShell; em outros shells, use `npm` no lugar de `npm.cmd`.

```powershell
composer install
npm.cmd ci
```

Use os locks existentes; não rode atualização de dependências como parte rotineira da instalação. O [.npmrc](../.npmrc) desativa scripts de instalação; o build é executado explicitamente.

Se ainda não houver `.env`, copie [.env.example](../.env.example):

```powershell
Copy-Item .env.example .env
php artisan key:generate --no-interaction
```

Não sobrescreva um ambiente existente nem regenere sua chave. A documentação não deve conter chaves, senhas locais ou dados reais.

## Banco local

Inicie o MySQL. Crie um banco vazio `banco_de_leite_dev` com charset `utf8mb4` e collation `utf8mb4_unicode_ci`, separado do legado. Ajuste no seu `.env` as variáveis `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD` conforme suas credenciais. O exemplo versionado contém apenas valores de desenvolvimento.

```powershell
php artisan config:clear
php artisan migrate --no-interaction
php artisan migrate:status
npm.cmd run build
```

Não importe o schema legado por cima das migrations. Não use `migrate:fresh` em banco com dados a preservar.

Depois das migrations, provisione o catálogo de permissões com `php artisan db:seed --class=PermissaoSeeder --no-interaction`. O seeder é idempotente e não concede permissões a ninguém: o admin acessa todos os módulos automaticamente, e funcionários dependem de concessão explícita. Ainda não há tela para conceder permissões; consulte [Status](backend/STATUS.md#estado-atual--29102026).

O phpMyAdmin mostra o banco MySQL/MariaDB, não arquivos SQLite. O arquivo `database/database.sqlite` remanescente da preparação anterior não representa a conexão MySQL. Os testes usam outro banco: SQLite em memória.

### Dados fictícios opcionais

Em um banco local novo, o [seeder](../database/seeders/DatabaseSeeder.php) pode ser executado com `php artisan db:seed --no-interaction`. Ele cria duas contas fictícias internas: admin e funcionário. Não cria contas de doadora. Consulte o arquivo para os dados de demonstração; não os use em produção. O seeder usa `create` e não é idempotente: repeti-lo com esses e-mails existentes pode causar conflito de unicidade. Verifique antes e não apague tabelas para contornar o conflito. Contas criadas pela interface interna definem a senha pela recuperação por e-mail; configurar a entrega antes de uso real.

## Execução

```powershell
php artisan serve
```

Use o endereço informado no terminal. Mantenha MySQL e servidor ativos. O Apache do XAMPP é necessário para seu phpMyAdmin, mas não para servir a aplicação pelo Artisan.

Para atualizar CSS e JavaScript durante o desenvolvimento, rode `npm.cmd run dev` em outro terminal. Alternativamente, gere `npm.cmd run build` após alterações. Se `public/hot` apontar para um Vite encerrado, remova apenas esse arquivo gerado antes de usar o build.

O [.env.example](../.env.example) configura sessões, cache e fila em banco e e-mail em log. Envio SMTP real não está configurado pelo exemplo. Logs de recuperação podem conter links sensíveis; não os publique.

## Verificações recomendadas

```powershell
composer validate --strict
composer check-platform-reqs
php artisan route:list --except-vendor
php artisan test --compact
npm.cmd run build
```

O [phpunit.xml](../phpunit.xml) usa SQLite `:memory:`, cache/sessão em memória e notificações simuladas em parte dos testes. Uma execução verde não prova integração MySQL, entrega de e-mail nem comportamento visual. Para mudanças nesses pontos, faça verificações específicas e registre o resultado no PR.

No PowerShell, o wrapper `npm.ps1` pode ser bloqueado pela política de execução; use `npm.cmd` sem alterar a política do sistema.

### Testes em MariaDB exclusivo

Quatro testes de concorrência (agenda e relatórios) são ignorados em SQLite e exigem MariaDB com conexões independentes. As validações de módulo também foram repetidas em MariaDB. Use somente um banco descartável chamado exatamente `banco_de_leite_schema_test`, criado vazio com `utf8mb4`/`utf8mb4_unicode_ci`; a guarda em [TestCase](../tests/TestCase.php) recusa outros bancos, URLs externas e configuração em cache. Defina as variáveis apenas no processo, sem editar o `.env`:

```powershell
$env:APP_ENV = 'testing'; $env:DB_CONNECTION = 'mysql'; $env:DB_DATABASE = 'banco_de_leite_schema_test'; $env:DB_URL = ''
php vendor/bin/phpunit tests/Feature/AgendaConcurrencyTest.php tests/Feature/RelatorioVisitaConcurrencyTest.php
```

Feche o terminal ou remova as variáveis depois. Os testes recriam e revertem as tabelas desse banco; nunca aponte para `banco_de_leite_dev`.

Não execute `composer run setup` indiscriminadamente em ambiente existente: o script gera chave e aplica migrations. O script de criação de projeto ainda menciona SQLite. São pendências de alinhamento; não foram alterados nesta tarefa.

Após receber alterações, reinstale pelos locks quando mudarem, aplique migrations revisadas e compile os assets quando necessário. Não reinstale Breeze sobre as views adaptadas.
