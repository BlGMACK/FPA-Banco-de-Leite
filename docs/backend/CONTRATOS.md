# Contratos entre backend e frontend

## Natureza e estado

A superfície atual e suas convenções abaixo são **implementadas, extraídas do código**; não representam aceite formal de produto. A seção de admissão ao final é **proposta**, baseada nas diretrizes informadas pelo usuário em 23/10/2026. Acordos precisam de decisão identificada. A integração atual usa HTML Blade, formulários e sessão, sem API REST de negócio.

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

## Permissões propostas para o fluxo de admissão

Origem: **G** = diretrizes do guia do usuário, registradas em [R1](ROADMAP.md); **P** = detalhamento proposto abaixo. G exige acesso da interessada apenas aos próprios dados e atuação profissional autorizada. Os papéis atuais `admin`, `funcionario` e `doadora` não implementam as permissões desta matriz.

“Equipe de atendimento”, “preenchedor”, “revisor” e “decisor” são atribuições funcionais propostas, **não novos perfis aprovados**. Definir profissionais, acúmulo de atribuições, concessão/revogação e representação técnica dessas permissões em D5. Não se propõe instalar pacote de permissões nem criar arquitetura nova.

| Ação | Interessada autenticada e vinculada | Equipe de atendimento autorizada | Profissional com atribuição clínica específica | Admin sem atribuição clínica |
| --- | --- | --- | --- | --- |
| Criar solicitação própria | Sim, conforme D1 e validação dos campos. | Em nome da interessada somente se D2 aprovado. | Mesma condição da equipe; atribuição clínica não concede automaticamente outras ações. | Somente com atribuição de atendimento e D2. |
| Consultar identificação, solicitação e agenda | Somente próprias. | Registros dentro do escopo de atendimento atribuído. | Apenas registros necessários à sua atuação autorizada. | Somente com atribuição operacional correspondente. |
| Corrigir dados básicos | Proposta: próprios antes da análise; após isso, pedir correção à equipe. Regra a confirmar. | Se autorizado, preservando histórico. | Apenas se também autorizado a corrigir cadastro. | Não por simples papel; depende da atribuição. |
| Analisar pedido, recusar administrativamente e confirmar agenda | Não. | Sim, conforme D5/D8 e com autoria/motivo. | Somente se também tiver atribuição de atendimento. | Somente com atribuição de atendimento. |
| Registrar atendimento | Não. | Conforme D5. | Conforme D5. | Somente com atribuição correspondente. |
| Preencher/concluir triagem | Não pelo formulário público. | Apenas se tiver atribuição de preenchedor. | Somente com atribuição de preenchedor. | Não por simples papel. |
| Ler/revisar dados de saúde e exames | Apenas informações próprias liberadas segundo D11; conteúdo detalhado a definir. | Sem acesso por padrão. | Conforme atribuição e registro, D5. | Sem acesso por padrão. |
| Registrar decisão final | Não. | Não por atribuição administrativa. | Apenas decisor autorizado, com requisitos satisfeitos. | Não por simples papel. |
| Ver resultado comunicado | Próprio, conforme D11. | Resultado operacional mínimo necessário; sem justificativa clínica por padrão. | Conforme atribuição. | Conforme atribuição; sem justificativa clínica por padrão. |
| Ler justificativas restritas e histórico clínico | Conteúdo e canal de acesso a definir em D11; não exibir automaticamente. | Não por padrão. | Apenas atribuição específica para o registro. | Não por simples papel. |
| Apagar histórico de recusa/decisão | Não. | Não. | Não. | Não. Retenção/correção segue D10. |

Visitante sem conta: orientações podem ser públicas; envio de solicitação depende de D1. Se essa entrada for escolhida, consulta posterior exige mecanismo de identidade/acesso acordado. Não permitir consulta pública por CPF, e-mail, protocolo ou ID isolado.

Proposta de autorização: verificar ação **e** vínculo/escopo do registro no servidor em listagens, detalhes, downloads e gravações. IDs da interessada proprietária, autor, aprovador e estado técnico são determinados pelo servidor; não confiar em campos ocultos enviados pelo navegador. O papel doadora, sozinho, não comprova propriedade de um registro. O atual middleware de papel não realiza esse vínculo.

### Limites dos formulários propostos

| Etapa | Entradas admitidas após definição dos campos | Valores controlados pelo servidor | Saída pretendida |
| --- | --- | --- | --- |
| Solicitação | Identificação, contato, endereço mínimo acordado e preferência de data. | Protocolo, identidade/vínculo validado, estado inicial, autor e instante; nenhum resultado clínico. | Confirmação de recebimento; explicar que data desejada não confirma agenda. |
| Análise e agenda | Resultado administrativo, motivo quando necessário, horário/local/modalidade combinados. | Autorização, transição válida, confirmador e instante; vínculo à solicitação. | Situação atualizada e agendamento registrado. |
| Atendimento e triagem | Dados efetivamente coletados e conteúdo autorizado de triagem a definir. | Autor, versão, vínculo e transição; preenchimento não aprova. | Atendimento/triagem registrados, com pendências identificadas. |
| Avaliação | Dados e parecer técnico definidos com o serviço. | Avaliador, versão de triagem e validação de completude. | Avaliação em andamento, aguardando complementação ou concluída. |
| Decisão | Resultado e justificativa conforme D5/D6/D11. | Decisor, instante, avaliação de origem e habilitação atômica quando aprovada. | Decisão preservada; mensagem à interessada separada da justificativa restrita. |

Transporte mantém Laravel/Blade, formulários, sessão e redirecionamentos. Rotas, nomes de views, campos finais, mensagens, bags e canais de notificação permanecem a definir em R5. As linhas acima não são endpoints disponíveis.

### Falhas e critérios para detalhar R5

- Erros de campos: apresentar junto ao campo e preservar apenas entradas permitidas, sem gravar dados parciais.
- Falta de sessão: exigir autenticação nas ações privadas; eventual entrada pública depende de D1.
- Acesso fora do escopo: negar no servidor; decidir resposta 403/404 para registros alheios sem expor sua existência.
- Estado alterado por outro operador ou envio repetido: não duplicar decisão, agenda ativa ou doadora; informar conflito/resultado já registrado.
- Requisitos clínicos pendentes: impedir decisão final; não converter ausência de informação em resultado desfavorável.
- Justificativas e dados de saúde: não incluir em mensagens genéricas, URLs ou logs comuns.
- Exibir estados e decisões conforme permissão, distinguindo recebimento, agendamento confirmado e habilitação.

Critérios funcionais estão em [R1](ROADMAP.md); integridade e estados em [Modelagem](MODELAGEM.md). A implementação depende das decisões abertas, especialmente entrada com/sem conta, profissionais, campos e critérios de avaliação.
