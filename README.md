# FPA - Banco de Leite Humano

Sistema de virtualização de dados do Banco de Leite Humano de Marília. Back-end em PHP (POO) + MySQL/MariaDB, seguindo o padrão PSR-4 de autoload.

## Como rodar

1. Clone dentro da pasta `htdocs` do XAMPP.
2. Copie `Config/config.example.php` para `Config/config.php` (só edite os valores se seu MySQL não for o padrão do XAMPP).
3. Rode `schema.sql` — ele cria o banco de dados sozinho:
```
   mysql -u root < schema.sql
```
   ou cole o conteúdo na aba "SQL" do phpMyAdmin.
4. Instale as dependências:
```
   composer install
```
5. Teste:
```
   php index.php
```

## Como editar

- **Nova tabela/entidade**: adicione o `CREATE TABLE` em `schema.sql` e crie a classe correspondente em `Models/`, seguindo o padrão de `Doadora.php` (métodos `criar`, `buscarPorId`, `atualizar`, `excluir`, sempre com PDO e prepared statements).
- **Credencial de banco diferente na sua máquina**: edite só o seu `Config/config.php` local. Nunca edite `config.example.php` — ele é o modelo compartilhado no Git.

## Estrutura e observações

| Arquivo/pasta | O que é |
|---|---|
| `Config/Conexao.php` | Conexão única (Singleton) com o banco; lê as credenciais de `Config/config.php`. |
| `Config/config.example.php` | Modelo de configuração — este **vai** para o Git. |
| `Config/config.php` | Configuração real de cada máquina — **não vai** para o Git (está no `.gitignore`). |
| `Models/` | Uma classe por tabela. Nomeie a pasta e os arquivos sempre com a mesma letra maiúscula/minúscula do `namespace` — PSR-4 é sensível a isso no Linux/Mac, mesmo passando despercebido no Windows. |
| `schema.sql` | Cria o banco e todas as tabelas, na ordem certa (uma `FOREIGN KEY` só funciona se a tabela referenciada já existir antes dela). |
| `index.php` | Script de teste manual do CRUD — não é a aplicação final, serve só pra confirmar que a conexão e as classes estão funcionando. |


obs: Groot é o maior
