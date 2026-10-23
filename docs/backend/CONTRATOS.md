# Contratos entre backend e frontend

## Natureza e estado

Os contratos abaixo são **implementados, extraídos do código**; não representam aceite formal de produto. Acordos futuros precisam de decisão identificada. A integração atual usa HTML Blade, formulários e sessão, sem API REST de negócio.

Fontes: [rotas web](../../routes/web.php), [rotas de autenticação](../../routes/auth.php), [controllers Auth](../../app/Http/Controllers/Auth), [ProfileController](../../app/Http/Controllers/ProfileController.php) e [views](../../resources/views).

## Convenções de transporte implementadas

Formulários usam `@csrf`; ações PATCH, PUT e DELETE usam POST com `@method` no HTML. O backend valida os campos, persiste e retorna redirecionamentos; erros web são exibidos por `$errors` e valores anteriores por `old()`. Redirecionamento não deve ser tratado como envelope JSON.

Requisições que esperam JSON têm tratamento de erros previsto em [bootstrap/app.php](../../bootstrap/app.php), mas não há contrato de sucesso JSON de negócio documentado.

## Superfície atual

| Acesso | Método e caminho | Entrada / saída ou efeito |
| --- | --- | --- |
| Público | GET `/` | View `inicio`, demonstração Bootstrap. |
| Visitante (`guest`) | GET/POST `/register` | View `auth.register`; POST recebe `name`, `email`, `password`, `password_confirmation`; cria conta e autentica; redireciona ao painel. |
| Visitante | GET/POST `/login` | View `auth.login`; POST recebe `email`, `password` e `remember` opcional; autentica e regenera sessão; destino pretendido ou painel. |
| Visitante | GET/POST `/forgot-password` | View `auth.forgot-password`; POST recebe `email`; solicita link; volta com status ou erro. |
| Visitante | GET `/reset-password/{token}`, POST `/reset-password` | View `auth.reset-password` recebe `request`; POST recebe `token`, `email`, `password`, `password_confirmation`; sucesso redireciona ao login. |
| Autenticado | GET `/dashboard` | View `dashboard`; rota declara `verified`, com a ressalva do model abaixo. |
| Autenticado | GET `/profile` | View `profile.edit` recebe `user`. |
| Autenticado | PATCH `/profile` | `name`, `email`; sucesso volta ao perfil; mudança de e-mail limpa sua data de verificação. |
| Autenticado | PUT `/password` | `current_password`, `password`, `password_confirmation`; volta à origem com status. |
| Autenticado | DELETE `/profile` | `password` atual; exclusão física da própria conta, invalidação da sessão e redirecionamento para início. |
| Autenticado | GET/POST `/confirm-password` | View `auth.confirm-password`; POST recebe `password`; confirma sessão e redireciona ao destino pretendido/painel. |
| Autenticado | GET `/verify-email` | View `auth.verify-email` ou redirecionamento ao painel. |
| Autenticado | GET `/verify-email/{id}/{hash}` | URL assinada, limite `6,1`; marca verificação e redireciona com `?verified=1`. |
| Autenticado | POST `/email/verification-notification` | Limite `6,1`; solicita notificação ou redireciona se já verificado. |
| Autenticado | POST `/logout` | Encerra/invalida sessão e volta ao início. |
| Admin | GET `/admin-teste` | View `teste-papel`, variável `papel`. Apoio de desenvolvimento. |
| Admin ou funcionário | GET `/interno-teste` | Mesma view com descrição do grupo. Apoio de desenvolvimento. |

[bootstrap/app.php](../../bootstrap/app.php) também registra GET `/up` como health check do framework, sem contrato de domínio.

## Campos, mensagens e permissões

Cadastro e atualização de perfil exigem nome e e-mail, com limite de 255 caracteres; e-mail válido, minúsculo e único (perfil ignora a própria conta). Senhas novas usam confirmação e `Password::defaults()`; não há política própria de complexidade documentada no código. Login limita tentativas por e-mail/IP.

| Fluxo | Sinal para a view |
| --- | --- |
| Perfil salvo | Sessão `status=profile-updated`; erros no bag padrão. |
| Senha alterada | Sessão `status=password-updated`; erros no bag `updatePassword`. |
| Exclusão com senha inválida | Bag `userDeletion`; modal reabre conforme erros. |
| Notificação de verificação solicitada | Sessão `status=verification-link-sent`. |
| Recuperação de senha | Status traduzido do broker ou erro de `email`; não assumir texto fixo. |

A integração do modal usa eventos `open-modal`/`close-modal`, identificador `confirm-user-deletion` e atributos `data-blh-modal`/`data-show`: [JavaScript](../../resources/js/app.js), [componente](../../resources/views/components/modal.blade.php), [formulário](../../resources/views/profile/partials/delete-user-form.blade.php).

[User](../../app/Models/User.php) não implementa `MustVerifyEmail`; portanto, não se deve prometer bloqueio de acesso por e-mail não verificado. O padrão de novo cadastro é `doadora`, definido no banco; não há campo de papel no contrato público.

| Rota de apoio | Admin | Funcionário | Doadora | Sem sessão |
| --- | --- | --- | --- | --- |
| `/admin-teste` | Permitido | 403 | 403 | Login |
| `/interno-teste` | Permitido | Permitido | 403 | Login |

A matriz descreve o código do [middleware](../../app/Http/Middleware/EnsureUserHasRole.php), sem afirmar execução de teste completo. Esconder um item no menu não autoriza o usuário no servidor. Papéis não definem ainda permissões de módulos futuros.

## Modelo para contratos futuros — proposto

Copie este modelo para a seção do fluxo quando houver trabalho autorizado; deixe lacunas como “a definir”.

| Campo | Preenchimento |
| --- | --- |
| Fluxo e objetivo | a definir |
| Estado | proposto; promover a acordado apenas com origem da decisão |
| Decisão / referência / data | a definir |
| Participantes da revisão | a definir |
| Backend: rota, nome, verbo e middleware | a definir |
| Frontend: view, componente e interação | a definir |
| Transporte | HTML/formulário/redirecionamento na arquitetura atual; outra opção depende de necessidade acordada |
| Entradas | nomes, tipos, limites, obrigatoriedade, formato e exemplo fictício |
| Saídas | view e variáveis, destino de redirecionamento ou formato acordado |
| Validação e falhas | campos, bags, códigos HTTP, mensagens/status e comportamento da tela |
| Permissões | papéis, propriedade do registro e casos negados |
| Persistência e efeitos | transação, estados, repetições, notificações e auditoria conforme necessidade |
| Compatibilidade | telas afetadas e estratégia de mudança |
| Aceite e evidência | cenários positivos/negativos, teste backend e validação frontend, data e ambiente |

Nenhum fluxo futuro está aprovado por este modelo. Atualize contrato, implementação e documentação afetada no mesmo PR quando o comportamento mudar. Consulte [Roadmap](ROADMAP.md) e [Padrões](../PADROES.md).
