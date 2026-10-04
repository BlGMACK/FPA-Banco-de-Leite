# Aplicar a adaptação do Breeze para Bootstrap

## O que mudou

- Layouts público e autenticado, navegação responsiva, telas de autenticação, painel e perfil usam Bootstrap.
- Componentes de formulário e modal de exclusão foram adaptados.
- Alpine foi mantido para eventos e mensagens dos formulários do Breeze.
- Tailwind e seus plugins foram removidos das dependências; package-lock.json foi atualizado.
- Controladores, model User, middleware, migrations, seeders e rotas foram preservados byte a byte em relação ao ZIP recebido.

## Como aplicar na sua máquina

1. Registre seu estado atual em um commit.
2. Extraia o ZIP fora do repositório.
3. Copie o conteúdo da pasta FPA-Banco-de-Leite extraída para a raiz do seu projeto, substituindo os arquivos correspondentes. Preserve sua pasta .git, seu .env local, vendor e os arquivos locais de dados.
4. Apague do projeto antigo estes dois arquivos, que foram removidos na adaptação:
   - tailwind.config.js
   - postcss.config.js
5. Encerre os terminais do Vite/Artisan antes de atualizar as dependências. Se existir public/hot sem um servidor Vite rodando, remova-o.
6. Na raiz, execute:

```powershell
npm.cmd install
npm.cmd run build
php artisan view:clear
php artisan config:clear
php artisan serve
```

Não é necessário recriar o banco, rodar migrate:fresh ou repetir o seeder. Não regenere APP_KEY. O ZIP não inclui .env, dependências instaladas, logs, cache compilado nem dados locais.

## Checklist de teste

- Acesse /login e teste uma senha incorreta: a mensagem de erro deve aparecer.
- Confirme login/logout e a matriz de acesso aos caminhos /admin-teste e /interno-teste para os três usuários de teste.
- Abra /profile, salve nome/e-mail e confirme a mensagem de sucesso.
- Teste mudança de senha com senha atual incorreta, depois com senha correta, usando uma conta fictícia.
- Abra o modal Excluir conta e cancele; a conta deve continuar disponível.
- Em uma conta descartável, teste senha de confirmação incorreta: o modal deve reabrir com o erro. Só confirme exclusão se quiser apagar essa conta de teste.
- Confira o menu em tela estreita.
- Cadastre uma conta fictícia: o perfil deve continuar doadora, conforme o padrão do banco e o controlador existente.
- Confira /forgot-password. O envio real depende de configuração de e-mail. Com MAIL_MAILER=log, o link vai para storage/logs/laravel.log, não para a caixa de entrada.

## Verificações realizadas aqui

- Build Vite concluído com Bootstrap.
- npm audit retornou zero vulnerabilidades na consulta realizada.
- Código PHP do backend e rotas preservados byte a byte.
- A renderização visual não foi executada: o download do navegador de teste falhou. Menu, modal e responsividade precisam de conferência no navegador local.
- A instalação de PHP foi bloqueada pela revisão automática do ambiente por envolver execução de um instalador remoto. Os testes Artisan/PHPUnit e a renderização real das views Laravel precisam ser executados localmente.

A verificação obrigatória de e-mail não foi ativada nesta adaptação: o model User recebido não implementa MustVerifyEmail. As telas e rotas do Breeze permanecem disponíveis, mas aplicar essa exigência ao acesso requer uma decisão e configuração separadas.
