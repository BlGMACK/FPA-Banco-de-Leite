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

### Hipótese de entrada sem conta e contato manual

D1/D11: preferência do usuário por formulário público e WhatsApp, ainda sem decisão coletiva final sobre portal. Esta proposta substitui a matriz anterior centrada em uma interessada autenticada. D2: o usuário confirmou a necessidade de cadastro pela equipe por telefone/presencial sem conta da interessada.

A interessada envia dados e recebe confirmação de recebimento, sem área de consulta neste recorte. O sistema não mostra cadastro/status ao informar CPF ou telefone. Correções de dados existentes precisam de conferência pela equipe; o formulário não autentica a pessoa nem sobrescreve registros por coincidência de identificadores.

WhatsApp manual é proposta técnica para concretizar a preferência de contato. A equipe mantém a situação do processo no sistema e registra as tentativas/contatos. Nenhuma API, automação ou envio foi autorizado. O usuário definiu WhatsApp disponível como requisito de abertura: selecionar um dos telefones informados e declarar disponibilidade. Sem isso, o pedido não pode ser aberto, inclusive pela equipe. E-mail/telefone são canais secundários a critério do funcionário; não dispensam esse requisito. Falha de contato descoberta depois não apaga nem recusa automaticamente um pedido já recebido. Mensagens devem informar o necessário à etapa após conferência do destinatário.

### Catálogo funcional de permissões — proposto

Funcionários usam contas individuais com permissões configuráveis. O usuário definiu uma função única para o tratamento de solicitações. Propõe-se o conjunto “Atendimento de solicitações”, que reúne consulta, cadastro/correção, conferência de alertas, análise administrativa e contato, atribuível a um ou mais funcionários. Não concede acesso clínico. A representação como conjunto de permissões evita alterar o enum de papéis por antecipação; nome técnico e implementação ainda são propostos. O administrador administra acessos; funcionários não concedem permissões a si próprios.

| Permissão funcional | Alcance proposto |
| --- | --- |
| Consultar cadastro | Identificação, contato e situação administrativa dentro do escopo concedido; não inclui prontuário técnico. |
| Registrar/corrigir solicitação | Cadastro interno por telefone/presencial e correção conferida, com autoria e histórico. |
| Analisar solicitação | Análise administrativa e recusa fundamentada conforme regras acordadas; não decide aptidão. |
| Gerenciar agenda | Confirmar, cancelar e reagendar conforme D8. |
| Registrar contato | Contato com a interessada e registro mínimo da tentativa/resultado. |
| Registrar atendimento | Fato do atendimento e responsável. |
| Preencher triagem | Dados de triagem no escopo permitido; não implica permissão de aprovação. |
| Consultar saúde | Informações técnicas necessárias à atuação, com acesso ao registro autorizado. |
| Avaliar saúde | Preenchimento/conclusão da avaliação; critérios profissionais dependem de D5/D6. |
| Registrar decisão | Decisão final fundamentada, com profissional autorizado e requisitos satisfeitos. |
| Consultar justificativas/histórico restrito | Acesso específico, sem inclusão automática na consulta administrativa. |
| Gerenciar funcionários/permissões | Criação/gestão de contas internas e concessão/revogação registrada. |

A função de atendimento reúne o tratamento dos pedidos; sua inclusão em gerência de agenda ainda depende de D8. Como exemplos para etapas posteriores, profissional de consultas pode acumular atendimento, triagem e avaliação; permissão de decisão é separada. Logística de produção mencionada pelo usuário pertence a módulos futuros: não gera acesso automático a saúde nem exige implementar produção agora.

Administrador com acesso amplo é **proposta do usuário**, substituindo a hipótese anterior de admin necessariamente sem acesso clínico. Confirmar se “fazer tudo” inclui apenas consultar/gerenciar ou também emitir decisões clínicas. Recomendação: permissões técnicas e autorização profissional devem ser explícitas; não permitir aprovação sem requisitos nem exclusão do histórico, mesmo pelo administrador.

Todas as permissões precisam de escopo de registros definido em D5 (por exemplo, todos os atendimentos da unidade ou somente os atribuídos). Conferir permissão no servidor em cada ação, inclusive consulta, exportação/download e gravação. Concessões/revogações registram responsável e data e devem valer nas próximas operações, inclusive em sessões já abertas.

### Recebimento público e identificação — proposto

- Somente dados mínimos acordados entram pelo formulário. Papel, estado, resultados, responsáveis e habilitação são controlados pelo servidor.
- Validar formato do CPF/telefone não verifica identidade. Correspondências ficam restritas à equipe; não devolver dados de cadastro encontrado.
- Receber pedidos com dados repetidos e sinalizá-los internamente, inclusive correspondências com cadastros completos. Isso substitui a hipótese de bloquear nova solicitação ativa. Telefone compartilhado ou divergência não provocam mesclagem automática.
- Limitação de frequência e controles contra automação complementam a deduplicação; limiares e mecanismo a definir, sem presumir que um CPF único impede spam.
- Usar resposta neutra para envios recebidos e possíveis duplicidades. Exemplo: “Recebemos suas informações. A equipe verificará o pedido e entrará em contato pelo canal informado.” Não afirmar aprovação ou confirmação de agenda.
- No contato, registrar destinatário conferido, funcionário, momento e resultado. Não informar avaliação a um número apenas porque foi digitado em um reenvio.
- Falta de resposta requer próxima ação da equipe, não conclusão automática do processo.

### Limites dos formulários propostos

| Etapa | Entradas admitidas após definição dos campos | Valores controlados pelo servidor | Saída pretendida |
| --- | --- | --- | --- |
| Solicitação | Nome completo, CPF, fixo e/ou celular, e-mail, município/bairro, endereço completo, número selecionado para WhatsApp, declaração de disponibilidade e detalhes opcionais. | Protocolo de recebimento, estado inicial, canal, autor interno quando houver e instante; identidade sujeita a conferência, nenhum resultado clínico. | Confirmação de recebimento; explicar que data desejada não confirma agenda. |
| Análise e agenda | Resultado administrativo, motivo quando necessário, horário/local/modalidade combinados. | Autorização, transição válida, confirmador e instante; vínculo à solicitação. | Situação atualizada e agendamento registrado. |
| Atendimento e triagem | Relatório narrativo da visita, formato inicial indicado pelo usuário. | Autor, versão, vínculo e transição; preenchimento não aprova. | Relatório disponível para análise; documentos/exames só serão solicitados quando indicados depois dessa análise. |
| Avaliação | Dados e parecer técnico definidos com o serviço. | Avaliador, versão de triagem e validação de completude. | Avaliação em andamento, aguardando complementação ou concluída. |
| Decisão | Resultado e justificativa conforme D5/D6/D11. | Decisor, instante, avaliação de origem e habilitação atômica quando aprovada. | Decisão preservada; mensagem à interessada separada da justificativa restrita. |

Transporte mantém Laravel/Blade, formulários, sessão e redirecionamentos. Rotas, nomes de views, limites finais dos campos, mensagens, bags e detalhes de contato permanecem a definir em R5; WhatsApp manual é a hipótese preferida. As linhas acima não são endpoints disponíveis.

### Falhas e critérios para detalhar R5

- Erros de campos: apresentar junto ao campo e preservar apenas entradas permitidas, sem gravar dados parciais.
- Falta de sessão: exigir autenticação da equipe nas ações privadas; a hipótese D1 permite somente recebimento público, sem consulta ou edição pública de registros existentes.
- Acesso fora do escopo: negar no servidor; decidir resposta 403/404 para registros alheios sem expor sua existência.
- Estado alterado por outro operador ou envio repetido: não duplicar decisão, agenda ativa ou doadora; informar conflito/resultado já registrado.
- Requisitos clínicos pendentes: impedir decisão final; não converter ausência de informação em resultado desfavorável.
- Justificativas e dados de saúde: não incluir em mensagens genéricas, URLs ou logs comuns.
- Exibir estados e decisões conforme permissão, distinguindo recebimento, agendamento confirmado e habilitação.

Critérios funcionais estão em [R1](ROADMAP.md); integridade e estados em [Modelagem](MODELAGEM.md). A implementação depende das decisões abertas, com o primeiro recorte limitado à abertura, fila, conferência e contato; critérios clínicos são dependência das etapas posteriores. Ainda faltam confirmar limites técnicos e desfechos da análise de correspondências.

### Telas do primeiro recorte — especificação proposta

Campos e normalização estão em [Modelagem](MODELAGEM.md#dicionário-da-abertura-do-pedido). Fluxo: formulário público → confirmação de recebimento → fila interna → conferência de correspondências → contato registrado. As telas abaixo não foram implementadas.

| Tela | Conteúdo e comportamento |
| --- | --- |
| Pedido público | Campos definidos, fixo/celular com exigência de ao menos um, seleção do número de WhatsApp e declaração obrigatória. Detalhes adicionais em área ampla opcional. Erros por campo, sem exibir dados de cadastros existentes. |
| Recebimento | Mensagem neutra de recebimento, próxima ação da equipe e protocolo sem poder de consulta. Não prometer contato automático, agenda ou aprovação. |
| Pedidos de cadastro — interna | Todos os pedidos acessíveis à função de atendimento, em ordem de criação. Proposta: mais antigos primeiro, com ID como desempate, paginação e opção de inverter. Colunas: protocolo, criação, nome, situação, canal e um indicador de correspondências. |
| Detalhe do pedido | Dados declarados, campo adicional, histórico de contato/conferência e ação de registrar contato. Correção interna preserva autoria e valores anteriores. |
| Detalhes do alerta | Um painel reúne campos coincidentes, identificação dos outros pedidos/cadastros e links autorizados. Destacar os campos, usar rótulo/ícone além de cor, inclusive correspondências com cadastro completo. |
| Registro de contato | Funcionário, instante, canal, finalidade, resultado e próxima ação; WhatsApp principal, e-mail/telefone secundários. |

Filtro “Com correspondências” mostra apenas flags atuais de pedidos ativos, calculadas contra pedidos ativos e cadastros administrativamente concluídos. Arquivados não aparecem nesse filtro nem como destinos. Revisões antigas ficam no histórico separado; pode haver novo alerta se surgir outra correspondência elegível. O funcionário vê um único indicador por pedido, mesmo que vários campos/destinos coincidam.

Exemplo fictício: pedido P-104 tem CPF repetido em P-099, telefone repetido em P-080 e e-mail repetido no cadastro C-012. A linha de P-104 mostra **um** alerta; o detalhe mostra os três campos e seus destinos. Não é uma decisão automática de que os quatro registros representam a mesma pessoa.

A função de atendimento precisa consultar as correspondências administrativas em pedidos e cadastros completos. Esse acesso deve ser concedido explicitamente com a função; não estender para exames, avaliações ou justificativas clínicas. Destino fora do escopo autorizado não pode expor dados por meio do alerta.

Proposta de ações de conferência: “mesma pessoa”, “pessoas distintas” e “aguardando esclarecimento”, registrando funcionário, data e motivo. A coincidência continua registrada; não interpretar a flag como recusa automática.

### Resolver repetição após contato

**Confirmado pelo usuário em 23/10/2026:** o funcionário só confirma pedidos repetidos após contato com a interessada que realmente esclareça a dúvida. Ele pode arquivar ambos, justificando a decisão e retirando-os da análise. Preservar dados e histórico.

**Interação proposta:** selecionar os pedidos analisados, indicar o registro de contato que esclareceu a dúvida, registrar o resultado e preencher o motivo antes de confirmar o arquivamento. Mostrar os protocolos e o efeito da ação. Envio de mensagem ou tentativa sem resposta não habilita a resolução. Conferir esses requisitos no servidor, inclusive em envio direto da ação.

A tela deve distinguir “Arquivar pedidos analisados”, “Concluir cadastro” administrativamente e aprovação clínica. Propor visualização padrão dos pedidos em análise e acesso a “Todos/Arquivados”. Arquivamento retira o pedido das comparações e remove suas flags atuais, recalculando também os pedidos relacionados. A resolução anterior permanece consultável no histórico, sem badge de duplicidade atual no arquivado.

**Esclarecido pelo usuário:** “cadastro concluído” encerra a admissão administrativa e permite seguir para agendamento da visita; não aprova a doação. O funcionário decide qual pedido avança e qual arquiva após o contato esclarecedor. A ação registra principal, repetido, motivo e contato. Texto de apoio proposto: “Cadastro admitido para acompanhamento; avaliação para doação ainda pendente.” Reabertura de arquivado permanece pendente.

### WhatsApp e validação da abertura

A pessoa pode informar só fixo, só celular ou ambos. O seletor oferece apenas números preenchidos; ao remover o número selecionado, exigir nova escolha. A declaração informa que a pessoa pode receber contato pelo WhatsApp naquele número. Isso foi confirmado pelo usuário nesta interação.

Declaração e confirmação pela equipe são dados diferentes. Sem integração acordada, o sistema verifica preenchimento e formato, não a existência de conta WhatsApp. Não aprovar automaticamente identidade, número ou agenda. A indisponibilidade descoberta depois fica registrada e a equipe decide a próxima ação; pode usar canais secundários sem apagar o pedido.

Nova submissão independente com dados iguais gera pedido sinalizável. Propor proteção contra duplo clique/retransmissão da mesma operação, sem impedir novos pedidos apenas por igualdade dos dados. O mecanismo de repetição técnica e os controles contra spam serão definidos na implementação.

### Área de acompanhamento — definição funcional e proposta de tela

**Confirmado pelo usuário:** ao concluir administrativamente o cadastro, movê-lo para uma área de acompanhamento, onde as informações posteriores aparecem pendentes. Depois, o Banco entra em contato para marcar a visita presencial, conversar, esclarecer dúvidas e levantar informações sobre a situação da interessada e armazenamento/retirada do leite.

Fluxo exibido: **cadastro admitido → visita a agendar → visita agendada → relatório da visita → análise → documentos/exames indicados, se necessários → avaliação e decisão final**. A abertura da área não cria exames obrigatórios nem inicia coleta.

**Proposta de tela:** manter identificação e pedido de origem no cabeçalho e organizar pendências por etapa. Exibir claramente “Admissão administrativa concluída” e “Aprovação para doar pendente”. Separar filtros de pedidos de cadastro, cadastros em acompanhamento e pedidos arquivados. Não duplicar os dados da pessoa ao mudar de área.

| Etapa | Interação prevista | Distinção necessária |
| --- | --- | --- |
| Agendamento | Equipe registra contato e horário combinado. | Admissão não é agendamento confirmado. |
| Visita/triagem | Profissional registra relatório narrativo amplo, preservando autoria e versões. | Relatório é o formato inicial informado pelo usuário; campos estruturados podem ser revistos depois. |
| Análise do relatório | Profissional registra encaminhamento e indica documentos/exames quando necessários. | Não exigir exames antes dessa análise; conteúdo e critérios dependem do serviço. |
| Recebimento de arquivos | Interessada envia pelo canal de contato; funcionário autorizado anexa ao cadastro/requisito correspondente. | Sem conta/portal de upload da interessada e sem integração automática. |
| Avaliação dos arquivos | Profissional autorizado analisa e registra resultado ou necessidade de complementação. | Arquivo recebido não significa validado, requisito atendido ou doadora aprovada. |

No primeiro acesso ao acompanhamento, mostrar documentos/exames como “a definir após visita”, não como documentação faltante. A ausência de arquivo só vira pendência concreta após uma exigência registrada. Se houver desfecho desfavorável antes dos exames, preservar o fundamento e não exigir exames desnecessariamente; critérios e profissionais responsáveis continuam em D5/D6.

Relatórios e anexos têm permissões próprias. A função de atendimento não ganha leitura de saúde só por poder resolver um pedido repetido. Definir quem recebe/anexa documentos e quem os avalia antes de implementar essa etapa.
