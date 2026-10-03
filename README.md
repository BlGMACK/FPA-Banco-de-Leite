# FPA - Banco de Leite Humano

Sistema de gestão de dados do Banco de Leite Humano de Marília, em migração para uma aplicação web responsiva com **PHP, Laravel, Blade, Bootstrap e MySQL/MariaDB**.

O sistema será acessado pelo navegador em computadores e celulares, com contas e permissões conforme a função de cada usuário. A versão atual possui o ambiente básico configurado e uma página de teste do Bootstrap; os módulos de negócio ainda serão migrados.

## Pré-requisitos

- PHP compatível com Laravel 13 (8.3 a 8.5), com as extensões exigidas pelo framework, incluindo `pdo_mysql` para o banco. Recomenda-se habilitar `zip` para a instalação pelo Composer.
- Composer.
- Node.js LTS e npm.
- MySQL ou MariaDB (pode ser o fornecido pelo XAMPP).
- Git e um editor, como VS Code.

Os comandos abaixo usam o PowerShell do Windows. Usamos `npm.cmd` para evitar o bloqueio de `npm.ps1` pela política de execução do PowerShell. No Prompt de Comando, Linux ou macOS, pode-se usar `npm`.

## Primeira instalação

### 1. Clonar e acessar a aplicação

```powershell
git clone https://github.com/BlGMACK/FPA-Banco-de-Leite.git
cd FPA-Banco-de-Leite
git switch migracao-laravel
```

O repositório é privado e exige uma conta com acesso. O comando acima seleciona a branch `migracao-laravel`, onde o Laravel está na raiz. Se a equipe usar outro nome de branch, ajuste o comando. Quando essa estrutura for incorporada à `main`, selecione a `main`.

Todos os comandos seguintes devem ser executados na raiz do repositório, onde estão `artisan`, `composer.json` e `package.json`.

Não é necessário colocar o projeto em `htdocs`: durante o desenvolvimento, o site será executado com `php artisan serve`.

### 2. Instalar as dependências

```powershell
composer install
npm.cmd install
```

Bootstrap e Popper já estão declarados nas dependências do projeto; não precisam ser adicionados novamente.

### 3. Configurar o ambiente local

Na primeira instalação, copie o modelo e gere a chave da aplicação:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Se já existir um `.env` configurado, mantenha-o em vez de sobrescrevê-lo. A chave é gerada uma vez por instalação; não precisa ser regenerada a cada execução.

### 4. Criar e conectar o banco de dados

Inicie o **MySQL** no painel do XAMPP. Para usar o phpMyAdmin, inicie também o **Apache** e acesse <http://localhost/phpmyadmin>.

Crie um banco vazio chamado `banco_de_leite_dev`, com collation `utf8mb4_unicode_ci`. Se preferir, execute na aba SQL do phpMyAdmin:

```sql
CREATE DATABASE banco_de_leite_dev
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
```

No arquivo `.env` da aplicação Laravel, configure o bloco abaixo, sem `#` no início das linhas e sem duplicar as variáveis:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=banco_de_leite_dev
DB_USERNAME=root
DB_PASSWORD=
```

Esses valores consideram um ambiente local com porta 3306 e usuário `root` sem senha. Ajuste conforme a configuração da sua máquina. Essas credenciais são apenas para desenvolvimento local.

Mantenha o banco do projeto antigo separado. Não importe o `schema.sql` antigo no novo banco: as tabelas da aplicação Laravel serão criadas e atualizadas pelas migrations.

### 5. Criar as tabelas e preparar a interface

Salve o `.env` e execute:

```powershell
php artisan config:clear
php artisan migrate
npm.cmd run build
```

As migrations atuais criam as tabelas básicas do Laravel. As tabelas específicas do Banco de Leite serão acrescentadas durante a migração.

### 6. Executar o sistema

```powershell
php artisan serve
```

Acesse <http://localhost:8000>. Mantenha o terminal aberto enquanto utiliza o sistema; para encerrar, pressione `Ctrl + C`.

O MySQL precisa continuar ligado. O Apache não é necessário para executar o site pelo Artisan; ele é usado aqui apenas para acessar o phpMyAdmin.

## Execuções seguintes

Com o ambiente já configurado:

1. Inicie o MySQL no XAMPP.
2. Abra o terminal na raiz do repositório, onde está o arquivo `artisan`.
3. Execute `php artisan serve`.
4. Acesse <http://localhost:8000>.

Ao receber alterações da equipe, execute `composer install` se as dependências PHP mudarem, `npm.cmd install` se as dependências da interface mudarem e `php artisan migrate` se houver novas migrations. Após alterações no CSS ou JavaScript, execute `npm.cmd run build`.

Para atualizar a interface automaticamente durante o desenvolvimento, use dois terminais na pasta da aplicação:

```powershell
# Terminal 1
php artisan serve
```

```powershell
# Terminal 2
npm.cmd run dev
```

Com o Vite ativo, ele prepara as alterações de CSS e JavaScript sem exigir um build manual a cada edição. Continue acessando o endereço do Artisan.

## Como desenvolver

- **Tabelas:** crie migrations em `database/migrations`; aplique-as com `php artisan migrate`. Mudanças compartilhadas no banco devem ser registradas em novas migrations.
- **Entidades:** crie models em `app/Models`, usando o Eloquent do Laravel.
- **Operações e requisições:** organize os controladores em `app/Http/Controllers` e as validações em `app/Http/Requests`, conforme necessário.
- **Rotas web:** edite `routes/web.php`.
- **Páginas:** crie templates Blade em `resources/views`, utilizando Bootstrap.
- **Estilos e interações:** edite `resources/css/app.css` e `resources/js/app.js`.
- **Configuração local:** altere seu `.env`. Atualize `.env.example` quando surgir uma nova variável necessária à equipe, usando valores de exemplo sem segredos.

Exemplo de comando para gerar um model e sua migration:

```powershell
php artisan make:model Solicitacao -m
```

O comando gera os arquivos; os campos, relações e regras ainda precisam ser implementados.

## Estrutura

A aplicação Laravel fica diretamente na raiz do repositório. Os caminhos abaixo são relativos à raiz:

| Arquivo/pasta | Finalidade |
|---|---|
| `app/Models/` | Models e relações entre entidades. |
| `app/Http/Controllers/` | Controladores das operações do sistema. |
| `app/Http/Requests/` | Classes de validação de requisições, quando criadas. |
| `database/migrations/` | Histórico versionado da estrutura do banco. |
| `database/seeders/` | Inserção de dados iniciais ou de teste. |
| `routes/web.php` | Rotas acessadas pelo navegador. |
| `resources/views/` | Páginas Blade, incluindo `inicio.blade.php`. |
| `resources/css/app.css` | Import do CSS do Bootstrap e estilos próprios. |
| `resources/js/app.js` | Import do JavaScript do Bootstrap e interações próprias. |
| `public/` | Diretório público da aplicação. |
| `storage/` | Logs, cache e arquivos gerados pela aplicação. |
| `.env` | Configuração local; não deve ser enviado ao Git. |
| `.env.example` | Modelo de configuração compartilhado no Git. |
| `composer.json` e `composer.lock` | Dependências PHP e suas versões. |
| `package.json` e `package-lock.json` | Dependências da interface e suas versões. |
| `vite.config.js` | Configuração de preparação dos arquivos da interface. |
| `artisan` | Ferramenta de comandos do Laravel. |

A estrutura antiga (`Config/`, `Models/`, `schema.sql` e `index.php`) foi removida da raiz na branch da migração. Ela pode ser consultada no histórico do Git ou na versão anterior da `main`, enquanto essa versão estiver disponível.

## Git e trabalho em equipe

- Não envie `.env`, `vendor/` ou `node_modules/`. Esses itens devem estar ignorados pelo Git.
- Envie `.env.example`, os arquivos de código, as migrations e os arquivos de dependências, incluindo `composer.lock` e `package-lock.json`.
- Não envie credenciais, dados pessoais ou registros reais de saúde. Para testes, use dados fictícios.
- Desenvolva em uma branch e envie os commits com `git push`. O merge de um pull request incorpora as alterações na branch de destino, como `main`.
- Cada integrante possui seu próprio banco local. O Git compartilha código e migrations, não os registros cadastrados no banco.

## Observações

- Respeite letras maiúsculas e minúsculas nos nomes de arquivos, classes e namespaces; diferenças podem causar erros em Linux.
- O servidor do Artisan é destinado ao desenvolvimento. A instalação definitiva no Banco de Leite exigirá configuração própria de hospedagem e backups.
- Não execute `php artisan migrate:fresh` em um banco com dados que precisam ser preservados: esse comando apaga as tabelas antes de recriá-las.

## Após mover o Laravel para a raiz

Os caminhos padrão do Laravel e do Vite são relativos à aplicação. Mover toda a estrutura junta normalmente não exige alterar rotas, imports ou `vite.config.js`.

- Abra a raiz do repositório no VS Code e use novos terminais nesse diretório.
- Mantenha o `.env` local e a mesma `APP_KEY`; o banco MySQL não muda de local com a movimentação dos arquivos.
- Se houver configurações personalizadas com caminhos absolutos, ajuste-as para a nova localização.
- Se houver configuração própria de Apache ou virtual host, atualize o diretório público para a pasta `public` na nova localização. Isso não é necessário para `php artisan serve`.
- Se existir `public/hot` após encerrar o Vite, remova esse arquivo gerado antes de usar apenas o build.

Com os servidores de desenvolvimento encerrados, execute na nova raiz:

```powershell
php artisan config:clear
php artisan view:clear
npm.cmd run build
php artisan serve
```

Acesse <http://localhost:8000> e confira a página inicial e o botão de teste do Bootstrap.
