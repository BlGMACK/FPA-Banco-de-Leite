# Status do backend

Levantamento documental de 22/10/2026. **Implementado** indica presença no código; **validado** exige evidência de execução identificada. Este levantamento não homologou o sistema.

## Implementado

| Área | O que existe | Fonte |
| --- | --- | --- |
| Sessão | Login, logout, regeneração de sessão e limitação por e-mail/IP após cinco falhas de login. | [LoginRequest](../../app/Http/Requests/Auth/LoginRequest.php), [controller](../../app/Http/Controllers/Auth/AuthenticatedSessionController.php). |
| Cadastro | Nome, e-mail e senha; autenticação após cadastro. Papel padrão do banco é doadora; o formulário não escolhe papel. | [controller](../../app/Http/Controllers/Auth/RegisteredUserController.php), [migration de papel](../../database/migrations/2026_10_04_000001_add_role_to_users_table.php). |
| Perfil | Consulta, edição de nome/e-mail, troca de senha e exclusão da própria conta com senha atual. | [ProfileController](../../app/Http/Controllers/ProfileController.php), [ProfileUpdateRequest](../../app/Http/Requests/ProfileUpdateRequest.php), [PasswordController](../../app/Http/Controllers/Auth/PasswordController.php). |
| Recuperação de senha | Solicitação e redefinição por token usando o broker Laravel. | [rotas](../../routes/auth.php), [controllers](../../app/Http/Controllers/Auth). |
| E-mail | Rotas e lógica de verificação presentes; obrigatoriedade não ativada no model. | [User](../../app/Models/User.php), [rotas](../../routes/auth.php). |
| Acesso por papel | Admin, funcionário e doadora; demonstrações protegidas por middleware. | [middleware](../../app/Http/Middleware/EnsureUserHasRole.php), [rotas web](../../routes/web.php). |
| Persistência | Contas e tabelas de infraestrutura; MySQL no exemplo compartilhado. | [Modelagem](MODELAGEM.md), [.env.example](../../.env.example). |
| Testes | Autenticação, cadastro, senha, verificação e perfil, mais testes de exemplo. | [Feature](../../tests/Feature), [Unit](../../tests/Unit), [configuração](../../phpunit.xml). |

O model não implementa `MustVerifyEmail`: a presença do middleware `verified` no painel não torna a verificação obrigatória no estado atual.

## Evidências e seus limites

As execuções abaixo ocorreram **anteriormente nesta mesma sessão de preparação local**, em 22/10/2026. Não foram repetidas nesta tarefa documental e não há relatório versionado de CI.

| Estado | Evidência anterior | Limite |
| --- | --- | --- |
| Validado localmente | PHP 8.5.1; suíte retornou 25 testes aprovados e 61 assertions, usando SQLite em memória. | Antes da troca local para MySQL; não comprova matriz de papéis, MySQL ou navegador. |
| Validado localmente | Build Vite 8.3.5 concluído após correções de dependências; Composer validou o manifesto e os requisitos de plataforma naquele momento. | Não é auditoria contínua nem revisão visual. |
| Validado localmente | Após a mudança para MySQL, quatro migrations aplicadas e três contas fictícias listadas em `banco_de_leite_dev.users`. | Ambiente XAMPP local; sem teste automatizado de integração MySQL. |
| Validado localmente | Após a mudança para MySQL, respostas HTTP 200 em início, login e cadastro. | Verificação de resposta, sem interação ou login completo em navegador. |
| Verificado nesta tarefa | Leitura das fontes e comparação com os documentos. | Inspeção estática; verificações documentais finais são informadas na entrega. |

## Falta validar ou decidir

- Matriz completa de papéis com acessos permitidos/negados: não há teste específico encontrado para `EnsureUserHasRole`; condições da navegação não são evidência de autorização testada.
- Cadastro não permite selecionar papel no controller; falta teste explícito de tentativa de escalada e do papel padrão.
- Testes atuais de notificações usam fakes; entrega SMTP real e fluxos externos não foram validados.
- Modais, navegação móvel, acessibilidade e apresentação final precisam de validação visual.
- Rotas de apoio estão acessíveis conforme middleware; decidir sua permanência fora do desenvolvimento.
- Seeder não idempotente e scripts Composer ainda com geração de chave/menção a SQLite: revisar em tarefa de código autorizada.

## Não implementado no código analisado

Não foram encontrados módulos de cadastro de doadoras do domínio, coleta, recebimento, processamento, estoque, distribuição ou relatórios. Esses nomes são candidatos de discussão, não funcionalidades disponíveis nem escopo aprovado. Não foram encontrados contratos de API de negócio ou uma camada específica de auditoria de negócio.

Backend futuro e dependências: [Roadmap](ROADMAP.md). Telas existentes e seus limites: [Arquitetura](../ARQUITETURA.md). Detalhes de integração: [Contratos](CONTRATOS.md).
