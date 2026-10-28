# Contratos entre backend e frontend

## Natureza e estado

Regra vigente: [contas exclusivamente internas](#contas-exclusivamente-internas--decisão-e-implementação-de-27102026). Não há autocadastro nem conta de doadora. Rotas de agenda e criação interna estão descritas nas respectivas entregas abaixo; schema de contas ainda aguarda aplicação local por indisponibilidade do MySQL.

A superfície atual e suas convenções abaixo são **implementadas, extraídas do código**; não representam aceite formal de produto. A seção de admissão ao final é **proposta**, baseada nas diretrizes informadas pelo usuário em 23/10/2026. Acordos precisam de decisão identificada. A integração atual usa HTML Blade, formulários e sessão, sem API REST de negócio.

Fontes: [rotas web](../../routes/web.php), [rotas de autenticação](../../routes/auth.php), [controllers Auth](../../app/Http/Controllers/Auth), [ProfileController](../../app/Http/Controllers/ProfileController.php) e [views](../../resources/views).

## Convenções de transporte implementadas

Formulários usam `@csrf`; ações PATCH, PUT e DELETE usam POST com `@method` no HTML. O backend valida os campos, persiste e retorna redirecionamentos; erros web são exibidos por `$errors` e valores anteriores por `old()`. Redirecionamento não deve ser tratado como envelope JSON.

Requisições que esperam JSON têm tratamento de erros previsto em [bootstrap/app.php](../../bootstrap/app.php), mas não há contrato de sucesso JSON de negócio documentado.

## Superfície atual

| Acesso | Método e caminho | Entrada / saída ou efeito |
| --- | --- | --- |
| Público | GET `/` | View `inicio`, acesso à solicitação sem conta e ao login da equipe. |
| Público | GET/POST `/register` | Removidos; resposta 404. |
| Visitante | GET/POST `/login` | View `auth.login`; POST recebe `email`, `password` e `remember` opcional; autentica e regenera sessão; destino pretendido ou painel. |
| Visitante | GET/POST `/forgot-password` | View `auth.forgot-password`; POST recebe `email`; solicita link; volta com status ou erro. |
| Visitante | GET `/reset-password/{token}`, POST `/reset-password` | View `auth.reset-password` recebe `request`; POST recebe `token`, `email`, `password`, `password_confirmation`; sucesso redireciona ao login. |
| Autenticado | GET `/dashboard` | View `dashboard`; rota declara `verified`, com a ressalva do model abaixo. |
| Autenticado | GET `/profile` | View `profile.edit` recebe `user`. |
| Autenticado | PATCH `/profile` | `name`, `email`; sucesso volta ao perfil; mudança de e-mail limpa sua data de verificação. |
| Autenticado | PUT `/password` | `current_password`, `password`, `password_confirmation`; volta à origem com status. |
| Autenticado | DELETE `/profile` | `password` atual; exclui conta sem vínculos e encerra a sessão. Restrição por histórico preserva conta/sessão e retorna erro no bag `userDeletion`. |
| Autenticado | GET/POST `/confirm-password` | View `auth.confirm-password`; POST recebe `password`; confirma sessão e redireciona ao destino pretendido/painel. |
| Autenticado | GET `/verify-email` | View `auth.verify-email` ou redirecionamento ao painel. |
| Autenticado | GET `/verify-email/{id}/{hash}` | URL assinada, limite `6,1`; marca verificação e redireciona com `?verified=1`. |
| Autenticado | POST `/email/verification-notification` | Limite `6,1`; solicita notificação ou redireciona se já verificado. |
| Autenticado | POST `/logout` | Encerra/invalida sessão e volta ao início. |

[bootstrap/app.php](../../bootstrap/app.php) também registra GET `/up` como health check do framework, sem contrato de domínio.

## Campos, mensagens e permissões

Cadastro e atualização de perfil exigem nome e e-mail, com limite de 255 caracteres; e-mail válido, minúsculo e único (perfil ignora a própria conta). Senhas novas usam confirmação e `Password::defaults()`; não há política própria de complexidade documentada no código. Login limita tentativas por e-mail/IP.

| Fluxo | Sinal para a view |
| --- | --- |
| Perfil salvo | Sessão `status=profile-updated`; erros no bag padrão. |
| Senha alterada | Sessão `status=password-updated`; erros no bag `updatePassword`. |
| Exclusão com senha inválida ou conta vinculada a histórico | Bag `userDeletion`, campo `password`; modal reabre conforme erros. |
| Notificação de verificação solicitada | Sessão `status=verification-link-sent`. |
| Recuperação de senha | Status traduzido do broker ou erro de `email`; não assumir texto fixo. |

A integração do modal usa eventos `open-modal`/`close-modal`, identificador `confirm-user-deletion` e atributos `data-blh-modal`/`data-show`: [JavaScript](../../resources/js/app.js), [componente](../../resources/views/components/modal.blade.php), [formulário](../../resources/views/profile/partials/delete-user-form.blade.php).

[User](../../app/Models/User.php) não implementa `MustVerifyEmail`; portanto, não se deve prometer bloqueio de acesso por e-mail não verificado. Contas são internas, criadas por operador autorizado; o servidor fixa funcionário. O schema novo não tem padrão implícito de papel.

| Criação de funcionário | Admin | Funcionário com permissão | Funcionário sem permissão | Sem sessão |
| --- | --- | --- | --- | --- |
| GET formulário / POST criação | Permitido | Permitido | 403 | Login |

A matriz é exercitada por `FuncionarioCreationTest`. Rotas de demonstração foram removidas. Esconder um item no menu não autoriza o usuário no servidor; permissões de módulos continuam independentes.

## Base de models e autorização implementada em 24/10/2026

Escopo autorizado pelo usuário: models do recorte administrativo, autorização dos funcionários e preservação de autoria. Não foram criadas rotas, telas ou API de atendimento/gestão de permissões. As rotas de apoio por papel mantêm sua finalidade anterior.

- Gate `atender-solicitacoes`: admin atual ou funcionário atual com concessão não revogada desse código. Doadora, visitante, conta removida e funcionário sem concessão são negados. Escopo administrativo de uma unidade conforme R5; não concede acesso clínico.
- Gate `gerenciar-permissoes`: somente admin atual. Não existe `Gate::before` que libere todas as capacidades para admin; permissões futuras permanecem negadas até implementação explícita.
- Ambos consultam o banco em cada verificação, sem confiar em relações carregadas ou papel antigo na instância. As futuras rotas internas devem usar `auth` e `can:atender-solicitacoes`, além das pré-condições da operação. Gates não filtram consultas ou protegem automaticamente qualquer rota nova.
- `Permissao::conceder($funcionario, $responsavel, $motivo)` e `revogar(...)` verificam `gerenciar-permissoes`, exigem motivo e operam somente sobre `atender-solicitacoes`. Concessão exige destinatário funcionário; revogação pode retirar concessão antiga mesmo se o papel mudou. O responsável deve ser obtido da sessão autenticada pelo futuro controller, nunca de um ID enviado pelo cliente.
- A alteração e seu evento são gravados em transação; a linha do catálogo e o destinatário são bloqueados durante a operação. Pedido de concessão já ativa/revogação já efetiva retorna `false`, sem novo evento. Uma nova concessão após revogação atualiza a linha atual e preserva os eventos anteriores. Não substitui o contrato de idempotência das solicitações.
- [PermissaoSeeder](../../database/seeders/PermissaoSeeder.php) prepara somente o código de atendimento, de forma idempotente; não cria funcionários nem concede acessos. Foi exercitado nos testes e executado posteriormente no banco local com autorização, sem conexão automática ao `DatabaseSeeder`.
- `User.role` não aceita atribuição em massa. Alteração administrativa de papéis e criação de contas internas ainda não têm fluxo implementado; factories usam estados explícitos para dados fictícios.
- Exclusão com vínculos retorna a mensagem “Esta conta possui registros vinculados e não pode ser excluída. Procure a administração.” no bag `userDeletion`, campo `password`, preservando a sessão. A exclusão sem vínculos continua disponível. Não foi implementada desativação de conta.

Fontes: [models](../../app/Models), [Gates](../../app/Providers/AppServiceProvider.php), [perfil](../../app/Http/Controllers/ProfileController.php). Verificações e limites em [Status](STATUS.md); acompanhamento em [Segurança](../SEGURANCA.md). As especificações funcionais seguintes permanecem propostas onde ainda não implementadas.

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

Transporte mantém Laravel/Blade, formulários, sessão e redirecionamentos. A proposta técnica completa está em [R5](#r5--contrato-técnico-do-primeiro-recorte); o [recorte implementado de recebimento e fila](#recebimento-e-fila-implementados-em-24102026) delimita as rotas disponíveis. Limites clínicos e operações posteriores continuam pendentes.

### Falhas e critérios para detalhar R5

- Erros de campos: apresentar junto ao campo e preservar apenas entradas permitidas, sem gravar dados parciais.
- Falta de sessão: exigir autenticação da equipe nas ações privadas; a hipótese D1 permite somente recebimento público, sem consulta ou edição pública de registros existentes.
- Acesso fora do escopo: negar no servidor; decidir resposta 403/404 para registros alheios sem expor sua existência.
- Estado alterado por outro operador ou envio repetido: não duplicar decisão, agenda ativa ou doadora; informar conflito/resultado já registrado.
- Requisitos clínicos pendentes: impedir decisão final; não converter ausência de informação em resultado desfavorável.
- Justificativas e dados de saúde: não incluir em mensagens genéricas, URLs ou logs comuns.
- Exibir estados e decisões conforme permissão, distinguindo recebimento, agendamento confirmado e habilitação.

Critérios funcionais estão em [R1](ROADMAP.md); integridade e estados em [Modelagem](MODELAGEM.md). O primeiro recorte inclui abertura, fila, conferência, contato, resolução de repetição e admissão administrativa, com uma visão inicial de acompanhamento. Operações clínicas são posteriores. R5 detalha as operações deste recorte como proposta técnica.

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

## R5 — Contrato técnico do primeiro recorte

Elaborado em **24/10/2026**, por solicitação do usuário. **Estado: parcialmente implementado**, conforme recorte abaixo; os demais fluxos permanecem propostos. As regras funcionais confirmadas em R1 são a base; a implementação não equivale a homologação pela equipe.

### Recebimento e fila implementados em 24/10/2026

Implementação autorizada pelo usuário: [SolicitacaoController](../../app/Http/Controllers/SolicitacaoController.php), [StoreSolicitacaoRequest](../../app/Http/Requests/StoreSolicitacaoRequest.php), [Solicitacao](../../app/Models/Solicitacao.php), [views](../../resources/views/solicitacoes) e [rotas](../../routes/web.php).

- Disponíveis: GET `/doar`, POST `/pedidos-de-cadastro`, GET `/pedido-recebido`; GET/POST `/interno/solicitacoes`, GET `/interno/solicitacoes/nova` e GET `/interno/solicitacoes/{solicitacao}`. Os nomes seguem a tabela R5. As views são compartilhadas em `solicitacoes.create`, `recebida`, `index` e `show`, sem duplicar formulários internos/públicos.
- Mesmo formulário e validação no público/interno: CPF com dígitos verificadores, e-mail, endereço, ao menos um telefone com DDD, seleção de número preenchido e declaração de WhatsApp. Telefones aceitam 10 ou 11 dígitos, com máscara e prefixo brasileiro +55 normalizados; não verifica titularidade, existência do número ou acesso ao WhatsApp. Nome de comparação normaliza caixa/acentos/espaços; grafia informada é preservada no nome de exibição.
- Campos técnicos enviados pelo cliente não determinam estado, autoria, perfil ou vínculo. O público sempre grava `web` e autor nulo, mesmo com conta autenticada; interno exige canal `telefone/presencial` e usa o funcionário da sessão. Não cria conta nem identidade confirmada. Snapshot `dados_originais` guarda os dados iniciais normalizados e permitidos, não o corpo bruto da requisição.
- Token opaco `operacao_token` emitido no GET, protegido pela criptografia autenticada do Laravel, válido por duas horas e vinculado à sessão e ao contexto público/interno (incluindo autor interno). O UUID interno vai para `operacao_id`. Token inválido, expirado ou de outro contexto retorna 419. Nova abertura emite outra operação; não usar esse token como consulta/autenticação da interessada.
- Mesma operação e mesmos dados normalizados redirecionam para o resultado já produzido, sem duplicar pedido/evento. Dados diferentes com a mesma operação retornam 409. Nova operação com identificação repetida é aceita. Pedido e evento de recebimento são gravados na mesma transação; unicidade da operação trata colisão de gravação, sem alegar teste de concorrência real.
- Público confirma com mensagem neutra e marcador temporário de sessão, sem expor protocolo, CPF, detalhes ou existência de duplicidade. Acesso direto à confirmação mostra orientação genérica. Interno redireciona ao detalhe do pedido.
- Limites técnicos iniciais: 5 POSTs/minuto por IP no público e 30/minuto por usuário no interno; incluem tentativas inválidas e reenvios. Excesso retorna 429. Valores precisam de avaliação operacional, especialmente em redes compartilhadas. Não substituem proteção de infraestrutura.
- Fila/detalhe exigem `auth` e `can:atender-solicitacoes`; revogação vale nas próximas requisições. Fila usa filtros permitidos, paginação de 25, ordem por criação/ID (antigos primeiro). Paginação Bootstrap 5; links propagam somente filtros validados. Identificação completa fica no detalhe interno. Abrir uma tela não inicia análise.
- Um indicador por pedido ativo agrega correspondências por CPF, nome normalizado, e-mail sem distinção de caixa e telefones cruzados. Subconsultas calculam existência para fila/filtro; detalhe lista registros e campos coincidentes. Apenas pedidos `enviada/em_analise` e identidades com pedido `admitida` são alvos. Exclui arquivados, próprio pedido e identidade já vinculada; origem admitida não vira um segundo alvo. Correspondência não decide identidade, não mescla nem altera registros. Detalhe consulta todos os alvos coincidentes; desempenho/limite sob grande volume ainda não medido.
- Sem edição, contato, conferência resolutiva, arquivamento, admissão ou acompanhamento clínico nesta entrega. O detalhe não apresenta comandos dessas etapas. Interface de concessão de permissões continua pendente.

As migrations administrativas e o catálogo foram aplicados ao `banco_de_leite_dev` após autorização posterior em 24/10/2026. Nenhuma permissão foi concedida automaticamente a funcionários; administradores têm acesso conforme o Gate existente. Outros ambientes ainda exigem preparação do schema antes de disponibilizar esse código. Evidências e limites da ativação em [Status](STATUS.md#ativação-local--24102026) e [Segurança](../SEGURANCA.md).

### Atendimento administrativo implementado em 26/10/2026

Após autorização do usuário, o recorte avançou para início explícito de análise, contatos, conferências, resolução de repetições, admissão e consulta de acompanhamento. Fontes: [AtendimentoSolicitacaoController](../../app/Http/Controllers/AtendimentoSolicitacaoController.php), [AtendimentoSolicitacaoRequest](../../app/Http/Requests/AtendimentoSolicitacaoRequest.php), [ResolucaoSolicitacao](../../app/Models/ResolucaoSolicitacao.php) e [views](../../resources/views/solicitacoes). As propostas seguintes continuam como referência de planejamento; esta seção delimita o comportamento efetivo.

- Rotas POST de `analise`, `contatos`, `conferencias`, `resolucoes` e `admissao`, e GET de acompanhamentos, exigem sessão e `atender-solicitacoes`. IDs são consultados após autorização. Não há envio de WhatsApp pelo sistema.
- Entradas usam `pedidos[]` com `id` e `versao`; decisões podem incluir `cadastros[]` com esses mesmos campos. Limite técnico inicial: 50 registros de cada tipo por operação. Seleções adicionais são explícitas, com Alpine nos formulários. O início da análise aceita somente a origem.
- Contato registra canal, resultado, destinatário, finalidade, resumo, próxima ação, quando ocorreu e declarações de identidade conferida/dúvida esclarecida. Contato não realizado não aceita essas declarações. Autor vem da sessão. Contato e vínculos são gravados juntos; as versões dos pedidos abordados avançam. Contato não equivale a confirmação automática de canal ou identidade.
- Decisões exigem contato abrangendo todos os pedidos escolhidos, motivo e versões atuais. Exceto `aguardando_esclarecimento`, exigem contato realizado, identidade conferida e dúvida esclarecida. O pedido principal precisa estar em análise e ter dados obrigatórios e WhatsApp disponível.
- `pessoas_distintas` preserva o alerta como revisado, com assinatura dos dados de identificação examinados. Mudança desses dados, novo alvo ou registro posterior de dúvida pendente exige nova conferência. A evidência registra pares, campos coincidentes e versões; arquivamento preserva vínculos e histórico, mas retira o pedido das comparações atuais.
- Resolução admite o principal escolhido e arquiva os demais, ou arquiva os selecionados. Um pedido isolado só pode ser arquivado neste recorte com referência a cadastro já admitido. Não há arquivamento genérico, exclusão ou reabertura. Seleções de resolução precisam corresponder à origem; correspondências não esclarecidas fora do conjunto bloqueiam admissão.
- A identidade é criada a partir dos dados do principal ou vinculada explicitamente a cadastro existente selecionado e conferido, sem sobrescrevê-lo. Não é escolhida automaticamente por CPF. A operação não cria conta, aprovação clínica ou autorização de coleta.
- Resolução e admissão usam transação, travas nos pedidos selecionados em ordem de ID, versões, autoria e eventos. `operacao_id` é UUID apresentado no formulário interno: o mesmo identificador, autor, origem e conteúdo repetidos retornam a decisão anterior, sem duplicação; conteúdo diferente retorna 409. O UUID não concede acesso e não substitui sessão, Gate ou CSRF. Contato/análise retransmitidos com versão antiga retornam 409. Não há repetição automática de decisões.
- Erros de formulário usam bags `contato` e `decisao`; conflitos retornam 409. A tela de acompanhamento mostra origem, autoria e visita pendente; documentos/exames aguardam indicação posterior. Não contém funções clínicas simuladas.

Correção de dados por PATCH, agenda/relatório de visita e anexos continuam pendentes. As travas cobrem os registros selecionados; teste de concorrência real entre conexões, entradas simultâneas novas e desempenho sob volume permanecem pendentes. Datas dos formulários seguem o fuso configurado na aplicação, cuja adequação operacional deve ser revisada antes do uso real. Evidências e limites em [Status](STATUS.md) e [Segurança](../SEGURANCA.md).

### Próximo recorte: agenda e relatório de visita — proposta em 26/10/2026

**Estado:** especificação para revisão, não implementada. O usuário informou que testou o site e não encontrou problemas no fluxo atual; isso não confirma as regras novas abaixo. Fonte do fluxo: relato do usuário (G). A separação de agenda, atendimento e triagem já aparece na proposta de Modelagem (P), inspirada no legado (L). Não há PDF disponível nem critérios clínicos extraídos dele.

**Definições confirmadas pelo usuário em 26/10/2026, após as perguntas de refinamento:** visita nos dois locais, preferencialmente no domicílio para conhecer o ambiente da interessada, podendo ocorrer no Banco conforme a ocasião; funções/permissões separadas, acumuláveis pela mesma pessoa; relatório com rascunho editável e correções em novas versões após finalizar. São acordos de produto com o usuário, não confirmação de cargos profissionais pelo serviço. O restante do desenho abaixo permanece proposto.

**Escopo proposto:** registrar o horário combinado, reagendar/cancelar preservando histórico, registrar realização ou ausência e produzir relatório narrativo versionado. Solicitar documentos/exames, anexar arquivos, avaliar saúde e habilitar doação continuam em entregas posteriores. A solicitação permanece `admitida`; a situação da visita vem dos novos registros, sem ampliar automaticamente seu enum administrativo.

#### Campos e vínculos

| Registro proposto | Campos e origem | Regras propostas |
| --- | --- | --- |
| Agendamento | Solicitação admitida, início e fim previstos, local/modalidade, descrição do local, responsável pelo atendimento, autor/data da confirmação, referência ao contato que combinou a visita, versão e eventual agendamento substituído. | O contato deve pertencer ao acompanhamento e ter resultado realizado; mensagem apenas enviada não confirma. Modalidades acordadas: domicílio (preferencial) e Banco. Responsabilidade profissional e capacidade ainda precisam de definição. |
| Alteração da agenda | Agendamento, ação, motivo, estado anterior/novo, horários anteriores/novos, autor e instante. | Cancelamento, reagendamento e ausência preservam histórico. Motivo é operacional, sem justificativa clínica na agenda. |
| Atendimento | Agendamento, instante em que a visita ocorreu, profissional responsável e autor/data do registro. | Um atendimento por agendamento; não criar automaticamente quando chega o horário. Rejeitar realização futura ou em agendamento cancelado/substituído. |
| Relatório da visita | Atendimento, texto narrativo, situação, número da versão, versão anterior, autor e datas de criação/finalização. | Metadados estruturados e texto amplo conforme preferência do usuário. Não adicionar questionário clínico ou campos de exames por pressuposição. |

Identidade e pedido de origem continuam relacionados; não copiar nome/CPF/endereço para um cadastro independente. O local combinado pode ser registrado como descrição histórica da visita, sem atualizar silenciosamente o endereço canônico. Limites de texto, armazenamento de datas e índices ficam para o desenho físico após as decisões funcionais.

**Integração necessária:** o endpoint de contatos atual aceita apenas pedidos ativos e os incrementa por versão. Antes de confirmar agendas, será necessário um fluxo explícito de contato no acompanhamento admitido, com autoria e vínculo, sem reabrir a solicitação nem liberar todas as ações de pedidos para admitidos. O contato prévio de admissão não deve ser presumido como acordo sobre data/local da visita.

#### Estados e transições

| Entidade | Transição proposta | Condição |
| --- | --- | --- |
| Agenda | Sem agenda → `confirmado` | Contato esclareceu data/local; solicitação admitida e identidade vinculada. |
| Agenda | `confirmado` → `substituido` + novo `confirmado` | Reagendamento atômico, com motivo e vínculo ao anterior. |
| Agenda | `confirmado` → `cancelado` | Motivo e autoria; admissão preservada, sem recusa clínica. |
| Agenda | `confirmado` → `nao_realizado` | Proposta de nomenclatura para cobrir ambas as modalidades, sem presumir falta da interessada. Registro explícito após o horário e motivo operacional; não automatizar ausência. Submotivos e responsabilidades ainda a definir. |
| Agenda | `confirmado` → `realizado` | Registrar o fato do atendimento e profissional responsável, na mesma transação. |
| Relatório | Ausente → `rascunho` → `finalizado` | Ciclo acordado pelo usuário; propor texto não vazio para finalizar. Finalização não é parecer de aptidão. |
| Relatório | Finalizado → nova versão em rascunho | Correção com motivo, preservando o original; a última versão finalizada permanece distinguível da correção ainda em elaboração. |

Propor no máximo um agendamento confirmado por solicitação e impedir sobreposição do mesmo responsável quando ele for identificado. A política para dois acompanhamentos da mesma interessada, duração, deslocamento e capacidade da equipe precisa ser definida antes de implementar bloqueios de agenda. Não presumir disponibilidade apenas porque há horário livre no sistema.

#### Permissões e apresentação

- **Acordado:** funções de atendimento, agenda e registro profissional separadas, podendo ser acumuladas na mesma conta individual. Representar essa separação por permissões configuráveis, conforme a direção já adotada, sem novos valores de cargo no enum `users.role` e sem compartilhar logins.
- **Proposta técnica:** manter `atender-solicitacoes` e acrescentar capacidades próprias de agenda e relatório. Atendimento não recebe agenda automaticamente; quem exercer as duas funções recebe ambas por concessão explícita. Códigos e granularidade de leitura/escrita serão fechados no desenho técnico; o catálogo e as operações de concessão atuais ainda aceitam somente atendimento.
- Relatório permanece restrito a permissão profissional separada. Quem possui apenas agenda vê situação da visita e do relatório, sem receber o texto narrativo. Cargos habilitados, escopo por unidade/atribuição e quem pode finalizar/corrigir continuam a definir; o acordo de acúmulo não concede automaticamente atribuição clínica ao administrador ou a todo funcionário.
- Acompanhamento exibe próxima visita, histórico operacional e pendências derivadas. O relatório aparece somente para quem puder consultá-lo. Cancelamento/ausência devolvem a indicação de visita a agendar, mantendo a admissão.
- Documentos/exames continuam “a definir após análise da visita”. Finalizar relatório não solicita exames, não aprova doação e não inicia coleta.

#### Consistência e testes a implementar junto ao código

Escritas com CSRF, autorização, campos permitidos, versões e transação. Reagendamento não pode cancelar o antigo sem criar o novo; realização não pode duplicar atendimento; correção não pode sobrescrever relatório finalizado. Reenvios da mesma operação não duplicam registros. Adotar uma política explícita de fuso para exibição e persistência antes das migrations, com apresentação inequívoca do horário de Marília.

Cobertura proposta: negar acesso sem permissão; impedir leitura clínica por atendimento administrativo; bloquear agenda para pedido não admitido; exigir contato de confirmação válido; testar cancelamento/reagendamento e autoria; ausência sem atendimento fictício; conflitos de versão e sobreposição; realização sem duplicação; relatório vazio e correções versionadas; manter ausência de aprovação clínica após finalizar. Testar persistência em banco exclusivo e concorrência real de confirmações antes de afirmar que a agenda evita reservas simultâneas.

#### Decisões pendentes para fechar o recorte

1. Motivos operacionais e tratamento de visita não realizada, inclusive impedimento no domicílio. Modalidades já acordadas: domicílio preferencial e Banco.
2. Responsáveis, duração, deslocamento, capacidade e horários sobrepostos. A separação e o acúmulo das permissões já estão acordados.
3. Profissionais habilitados e escopo para ler, redigir, finalizar e corrigir. Rascunho e novas versões após finalizar já estão acordados.
4. Encaixe sem agendamento prévio, retorno após visita não realizada/cancelada e vários acompanhamentos da mesma pessoa.

As três questões iniciais receberam resposta explícita do usuário em 26/10/2026, registrada acima. Próximo trabalho técnico: detalhar persistência e autorização da agenda, incluindo contatos após admissão e testes da combinação de permissões. A implementação de regras clínicas depende das definições profissionais restantes; não preencher essas lacunas com critérios inventados.

### Contrato técnico proposto da agenda — 26/10/2026

**Atualização de implementação:** os models `Agendamento`, `ReservaAgenda` e `EventoAgendamento`, suas factories e o Gate `gerenciar-agenda` foram implementados após autorização do usuário. Solicitação e contato possuem relações com a agenda. Models mantêm proteção de atribuição em massa; os vínculos/casts não executam confirmação, reagendamento ou validações operacionais. Não há endpoints/telas de agenda nesta entrega.

O catálogo em `PermissaoSeeder` agora prepara atendimento e agenda, sem conceder acessos nem criar usuários. `Permissao::conceder/revogar` aceita explicitamente os dois códigos, com autoria, motivo e histórico; demais códigos continuam rejeitados. Admin tem acesso administrativo à agenda; funcionário depende de concessão ativa; doadora e visitante não recebem acesso. Atendimento e agenda são independentes e acumuláveis. A verificação consulta o papel/concessão atuais, inclusive com instância de usuário previamente carregada. Nenhum Gate clínico foi criado.

Nesta etapa, migrations e seeder **não foram executados no banco de desenvolvimento**. A preparação do catálogo não está conectada automaticamente ao `DatabaseSeeder`. Evidências em [Status](STATUS.md); a matriz de acesso abaixo está exercitada por testes de Gates e middleware em rota exclusiva de teste, não por endpoints de agenda em produção. As descrições seguintes preservam a proposta do fluxo web ainda pendente.

Este detalhamento atende à autorização de continuar a especificação de persistência/autorização. **Schema preparado após autorização posterior:** quatro migrations e testes de integridade, sem aplicação ao banco de desenvolvimento. **Fluxo web e autorização de agenda ainda não implementados.** Fonte física em [Modelagem](MODELAGEM.md#desenho-físico-proposto-para-agenda--26102026). Primeiro incremento: contato após admissão, confirmação, reagendamento, cancelamento e registro de visita não realizada. Registrar atendimento efetivamente realizado e editar/finalizar relatório ficam no incremento profissional seguinte; a separação evita conceder acesso clínico pela permissão de agenda.

#### Operações de backend implementadas em 26/10/2026

Após autorização para a próxima etapa, foram implementadas operações nos models, conforme a convenção administrativa existente. **Ainda não há controllers, rotas ou formulários de agenda:** os caminhos propostos abaixo continuam indisponíveis. Esta entrega não altera contatos de análise nem habilita ações clínicas.

| Operação disponível no código | Entrada principal | Efeito |
| --- | --- | --- |
| `ContatoSolicitacao::registrarAcompanhamento` | Pedido, autor, UUID, versão do pedido, canal/resultado/destinatário/finalidade/resumo/próxima ação e horário local do contato | Contato com contexto e autor definidos no servidor, vínculo único ao pedido admitido, atualização da versão sem reabrir análise. |
| `Agendamento::confirmar` | Pedido, autor, UUID, versão do pedido, contato realizado, responsável interno, modalidade/local, início/fim locais, motivo e declaração de combinação | Cria compromisso, reserva e evento atomicamente. |
| `Agendamento::reagendar` | Compromisso, autor, UUID, versão do compromisso e novos dados/contato/motivo | Preserva o anterior como substituído, cria sucessor e troca a reserva na mesma transação. |
| `Agendamento::cancelar` | Compromisso, autor, UUID, versão e motivo | Cancela, libera reserva e mantém admissão/histórico. |
| `Agendamento::registrarNaoRealizacao` | Compromisso, autor, UUID, versão e motivo | Registra não realização após o início previsto, libera reserva e mantém admissão; não cria atendimento clínico. |

Todas verificam `gerenciar-agenda`, inclusive antes de retornar um resultado repetido. Campos recebidos são validados por lista explícita; contexto, autoria, estados e vínculos são controlados no servidor. Erros de campos geram `ValidationException`; conflitos de versão, reserva, estado ou conteúdo de operação geram 409. A adaptação dessas exceções aos formulários e CSRF pertence ao próximo recorte web.

Horários de entrada usam `Y-m-d\TH:i`, interpretados em `America/Sao_Paulo` e persistidos em UTC, sem alterar o fuso global da aplicação. Contato futuro é rejeitado; confirmação/reagendamento exige início futuro e fim posterior. Reagendamento exige outro contato realizado do mesmo acompanhamento, ocorrido após a confirmação anterior (comparação com precisão de minuto, igual à entrada). Declarar o acordo não envia mensagens nem verifica WhatsApp automaticamente.

**Decisão do usuário nesta etapa:** bloquear e avisar sobre compromissos sobrepostos do mesmo funcionário. Implementado intervalo `[início, fim)`: compromissos adjacentes e horários distintos no mesmo dia são permitidos. A mensagem de validação informa que o funcionário já tem compromisso naquele horário. Deslocamento, capacidade de equipe e sobreposição de acompanhamentos diferentes da mesma interessada continuam sob definição/controle operacional; não são bloqueios adicionais implementados.

As operações travam responsáveis em ordem de ID, depois pedido, compromisso e reserva, e consultam conflitos com leitura bloqueante. Assim, também serializam confirmações para o mesmo responsável quando ainda não existem reservas. Pedido admite uma única reserva atual. UUID/hash incluem operação, origem, autor e dados validados; retransmissão idêntica retorna o contato/evento anterior, inclusive após passar o horário do compromisso, sem duplicação. Reenvio não ignora revogação da permissão. Nova operação com versão antiga é conflito, sem repetição automática.

Evidências de testes, incluindo os cenários de conexões independentes realmente executados, ficam em Status. Nenhuma migration/seeder aplicada ao desenvolvimento nesta entrega. Sem envio real de mensagens, agenda no navegador, relatório ou aprovação para doar.

#### Autorização por função

Código técnico proposto: `gerenciar-agenda`, acumulável com `atender-solicitacoes`. Reutilizar catálogo, concessões/revogações e auditoria existentes. Ampliar a lista explícita de códigos administrativos aceitos por `Permissao`; não aceitar qualquer texto enviado como permissão. Seeder adiciona catálogo de forma idempotente e não concede acesso. Administrador conserva alcance administrativo de agenda; funcionário precisa de concessão ativa. Relatório e decisão clínica não participam desse acesso automático.

| Acesso efetivo | Pedidos e decisões administrativas | Agenda e contato após admissão | Texto do relatório |
| --- | --- | --- | --- |
| Apenas atendimento | Permitido conforme contrato atual | Negado | Negado |
| Apenas agenda | Não libera fila/detalhe completo de pedidos | Permitido para acompanhamentos admitidos da unidade | Negado |
| Atendimento e agenda | Permitido | Permitido | Negado sem futura permissão profissional |
| Administrador | Permitido | Permitido no escopo administrativo | Depende do futuro contrato profissional |
| Visitante/doadora | Negado | Negado | Negado |

Quem possui somente agenda recebe uma visão operacional própria: identificação, telefone/WhatsApp, endereço necessário à visita, origem por referência e contatos de acompanhamento. Não reutilizar indiscriminadamente o detalhe atual, que contém justificativas e contatos de análise. Não exigir atendimento como permissão adicional oculta para navegar pela agenda. Revogação deve valer nas próximas requisições; não conceder permissões ao designar um responsável.

#### Fluxo web da agenda implementado em 27/10/2026

Após autorização do usuário, as sete rotas da tabela abaixo foram implementadas em `routes/web.php`, com `AgendaController` e views `agenda.*`. A tabela preserva a origem da proposta; agora descreve caminhos disponíveis no código, sujeitos à ativação das migrations/catálogo no ambiente local.

- A lista pagina compromissos (25 por página), com filtros validados de início/fim, modalidade, situação e responsável. Datas delimitam dias de Marília, convertidos para UTC; a seleção inclui intervalos que cruzam o período. Uma lista separada pagina admitidas sem reserva atual, inclusive após cancelamento/não realização. Os filtros de compromissos não afetam essa lista.
- Detalhe somente para pedido admitido com identidade vinculada. Contatos de acompanhamento e histórico paginados separadamente (15 por página); contatos de análise, CPF e justificativas administrativas não são apresentados. Quem possui somente agenda navega por essa visão sem precisar de atendimento. Links administrativos exigem a permissão correspondente.
- Formulários com CSRF e UUID, versão exibida e declaração explícita do combinado. As regras permanecem nos métodos transacionais; o controller aceita somente campos conhecidos. Valores escalares permitidos retornam ao formulário que falhou, com bags `contato`, `confirmacao`, `reagendamento`, `cancelamento` e `nao_realizacao`. Arrays inválidos não são refletidos em campos textuais.
- Sobreposição aparece como erro no formulário; conflitos de versão/operação retornam HTML 409 orientando reabrir e conferir. A view de erro também atende conflitos existentes, com orientação genérica fora da agenda. Contato enviado não gera mensagem automática.
- A navegação tem link Agenda para usuários autorizados. O acompanhamento administrativo aponta para a agenda mediante permissão, sem afirmar que toda visita continua não agendada. Relatório e decisões clínicas continuam indisponíveis.
- Validação HTTP automatizada registrada em Status. Revisão visual/responsividade pendente: nenhum navegador disponível na ferramenta nesta sessão. Não houve aplicação de migrations ou catálogo ao desenvolvimento.

#### Rotas web da agenda

Todas sob `auth` e `can:gerenciar-agenda`, com consulta dos IDs após autorização, formulários Blade, CSRF e redirecionamentos. Nomes/caminhos implementados conforme a tabela.

| Método / caminho | Nome | Efeito |
| --- | --- | --- |
| GET `/interno/agenda` | `interno.agenda.index` | Lista operacional paginada, filtros de período/modalidade/situação/responsável. |
| GET `/interno/agenda/acompanhamentos/{solicitacao}` | `interno.agenda.acompanhamentos.show` | Contexto mínimo do admitido, contatos de acompanhamento e histórico da agenda. |
| POST `/interno/agenda/acompanhamentos/{solicitacao}/contatos` | `interno.agenda.contatos.store` | Registra tentativa/contato no contexto de acompanhamento. |
| POST `/interno/agenda/acompanhamentos/{solicitacao}/agendamentos` | `interno.agenda.store` | Confirma horário/local combinado, criando reserva. |
| POST `/interno/agenda/{agendamento}/reagendamentos` | `interno.agenda.reagendamentos.store` | Substitui o compromisso preservando o anterior. |
| POST `/interno/agenda/{agendamento}/cancelamento` | `interno.agenda.cancelamento.store` | Cancela e libera reserva; preserva admissão. |
| POST `/interno/agenda/{agendamento}/nao-realizacao` | `interno.agenda.nao-realizacao.store` | Registra fato após horário previsto, motivo e liberação da reserva. |

IDs numéricos e segmentos estáticos diferenciados; sem consulta pública, exclusão física, envio automático de WhatsApp ou calendário externo. Links de agenda entram na navegação pela permissão correspondente. A tela atual de acompanhamento pode apontar para a visão de agenda somente quando autorizada.

#### Entradas, respostas e limites

- Contato: campos operacionais do contato atual, `operacao_id` e versão do pedido exibida. Servidor define contexto, vínculo ao admitido, autor e instante. Não exigir nova conferência de identidade só para registrar uma tentativa; confirmação do compromisso exige contato realizado e declaração explícita de que data/local foram combinados, registrada no evento da agenda.
- Confirmação: versão do pedido, contato de acompanhamento, modalidade `domicilio|banco`, descrição do local, início/fim locais com fuso fixo apresentado, responsável e `operacao_id`. Preferir domicílio no formulário, permitindo Banco; escolha precisa permanecer explícita. Contato deve pertencer ao mesmo acompanhamento e não pode ter resultado `tentado`, `sem_resposta` ou `numero_incorreto`.
- Reagendamento: versão do compromisso, novo contato confirmando a alteração, novos dados de agenda, motivo e operação. Cancelamento/não realização: versão, motivo e operação. Motivos não incluem parecer clínico. `responsavel_user_id` deve apontar para conta interna existente; fluxo para desativação e qualificação profissional continua pendente.
- Não realização não é recusa nem penalidade automática. Encaixe sem agenda, registro retroativo de visita realizada e aprovação para doação não entram neste incremento. Datas de confirmação novas são futuras; ausência só é registrada após o horário combinado. Fuso e validação precisam de testes específicos.
- Resposta válida: 302 para contexto operacional com mensagem clara. Entrada inválida: 302 com erros e valores seguros; sem sessão: login; sem permissão: 403; ID inexistente/incompatível: 404; versão/reserva/operação em conflito: 409 orientando recarregar; CSRF inválido: 419. Reenvio comprovado não duplica contato/agendamento/evento.
- Não haverá promessa de disponibilidade automática: duração, deslocamento e capacidade não foram definidos. O bloqueio de sobreposição do mesmo responsável descrito na Modelagem é proposta para revisão; se não adotado, a interface deve sinalizar conflitos e deixar explícita a limitação. Testar concorrência real antes de afirmar garantia contra dupla reserva.

#### Ordem da futura implementação

1. Migrations aditivas e testes de integridade em banco exclusivo; preservar contatos legados como análise.
2. Catálogo/Gate/concessões de agenda e testes da matriz acima, inclusive usuário com apenas agenda e revogação em sessão aberta.
3. Contatos de acompanhamento, modelos, transações de reserva e testes de conflito/retransmissão.
4. Formulários/lista/histórico e revisão visual, mantendo o relatório indisponível até definir o escopo profissional.

As decisões funcionais acordadas não transformam os nomes de tabelas, limites, rotas ou política de sobreposição deste contrato em implementação já aprovada ou executada. Permanecem pontos operacionais de capacidade/encaixes e atribuições profissionais, identificados na seção anterior.

### Limites e autorização

Entrega: receber pedido sem conta; listar e conferir; registrar contato; resolver repetição; concluir admissão administrativa; consultar acompanhamento com visita pendente. Agendar visita, redigir relatório, anexar exames e aprovar doação são operações de entregas posteriores. A tela inicial de acompanhamento apenas mostra a admissão e as próximas etapas; não oferece botões que simulem funções ainda indisponíveis.

**Permissão lógica proposta:** `atender-solicitacoes`, reunindo as ações administrativas definidas pelo usuário. Nas rotas internas, exigir sessão e essa permissão, além do acesso a cada registro envolvido. `role:admin,funcionario` sozinho é insuficiente. Proposta para este recorte de uma unidade: funcionários com a função acessam todos os pedidos e os dados administrativos dos cadastros dessa unidade; admin tem alcance administrativo amplo. Papel doadora e funcionário sem a função não têm acesso. Isso não concede permissão clínica.

A concessão da função depende de persistência e operação administrativa explícitas antes de disponibilizar o módulo; nunca expor campo de permissão no cadastro público. Concessão/revogação e proteção da autoria precisam ser contempladas na tarefa de implementação, sem instalar pacote por pressuposição.

### Rotas e nomes propostos

Todos os caminhos são relativos à aplicação, não endereços publicados. Escritas usam formulários web com CSRF; PATCH usa o mecanismo de método do formulário já adotado pelo projeto. Leituras não alteram estado nem confirmam contato.

| Método/caminho | Nome da rota | Acesso e efeito |
| --- | --- | --- |
| GET `/doar` | `solicitacoes.create` | Público; formulário sem login. |
| POST `/pedidos-de-cadastro` | `solicitacoes.store` | Público; valida e recebe pedido, com controle de frequência. |
| GET `/pedido-recebido` | `solicitacoes.recebida` | Público; confirmação genérica, sem parâmetro de CPF/ID para consulta. |
| GET `/interno/solicitacoes` | `interno.solicitacoes.index` | Função de atendimento; lista e filtros. |
| GET `/interno/solicitacoes/nova` | `interno.solicitacoes.create` | Função de atendimento; formulário para telefone/presencial. |
| POST `/interno/solicitacoes` | `interno.solicitacoes.store` | Função de atendimento; recebe pedido com canal e autoria. |
| GET `/interno/solicitacoes/{solicitacao}` | `interno.solicitacoes.show` | Detalhe, alerta atual, contatos e histórico administrativo autorizados. |
| PATCH `/interno/solicitacoes/{solicitacao}` | `interno.solicitacoes.update` | Corrige dados declarados de pedido ativo, com motivo e controle de versão. |
| POST `/interno/solicitacoes/{solicitacao}/analise` | `interno.solicitacoes.analise.store` | Marca início da análise; abrir a página não realiza essa ação. |
| POST `/interno/solicitacoes/{solicitacao}/contatos` | `interno.solicitacoes.contatos.store` | Registra tentativa ou contato realizado; não envia mensagem. |
| POST `/interno/solicitacoes/{solicitacao}/conferencias` | `interno.solicitacoes.conferencias.store` | Registra pessoas distintas ou necessidade de esclarecimento, sem admissão/arquivamento. |
| POST `/interno/solicitacoes/{solicitacao}/resolucoes` | `interno.solicitacoes.resolucoes.store` | Resolve repetição após contato: arquivar ambos ou admitir principal e arquivar repetido. |
| POST `/interno/solicitacoes/{solicitacao}/admissao` | `interno.solicitacoes.admissao.store` | Conclui cadastro administrativo sem repetição pendente; cria/vincula identidade confirmada. |
| GET `/interno/acompanhamentos` | `interno.acompanhamentos.index` | Lista administrativa dos cadastros admitidos. |
| GET `/interno/acompanhamentos/{solicitacao}` | `interno.acompanhamentos.show` | Identificação, pedido de origem, admissão e próximas etapas pendentes; nenhum dado clínico neste recorte. |

Não criar rota pública de consulta, API REST, exclusão física ou reabertura de arquivado neste recorte. A rota estática `nova` deve ser diferenciada do identificador de solicitação na implementação.

### Entradas e contrato do formulário

O [dicionário](MODELAGEM.md#dicionário-da-abertura-do-pedido) é a fonte de obrigatoriedade e limites. Nomes propostos para transporte: `nome_completo`, `cpf`, `telefone_fixo`, `telefone_celular`, `email_contato`, `municipio`, `bairro`, `uf`, `cep`, `logradouro`, `numero`, `complemento`, `whatsapp_telefone` (`fixo` ou `celular`), `whatsapp_disponivel` (declaração afirmativa) e `outros_detalhes`.

Aplicar as mesmas validações de dados no recebimento público e interno. Ao menos um telefone, escolha de WhatsApp que corresponda a número preenchido e declaração são obrigatórios. CPF com dígitos válidos não é identidade autenticada. Não exigir unicidade dos dados de identificação nos pedidos. Sem CNS, preferência de data ou anexos nesta primeira versão proposta.

O registro interno acrescenta `canal_origem=telefone|presencial`; no público, o servidor define `web`. Autor interno, instante, protocolo, status, identidade confirmada, alertas e permissões são calculados/controlados pelo servidor. Não aplicar campos adicionais enviados como `role`, `status`, `interessada_id` ou resultado clínico.

Propor `operacao_id` opaco emitido pelo servidor para distinguir retransmissão da mesma operação de novo envio deliberado. Ele não concede consulta nem autorização. Mesma operação com mesma entrada retorna o resultado seguro já produzido; outra entrada com a mesma chave é conflito. Nova operação com dados iguais cria novo pedido sujeito a alerta. Escopo, expiração, persistência e limite de frequência precisam ser definidos no desenho físico; não usar CPF como chave de bloqueio permanente.

Recebimento público bem-sucedido redireciona para `solicitacoes.recebida` com mensagem neutra. Protocolo pode aparecer apenas como comprovante temporário da submissão na própria sessão, sem CPF/dados e sem conceder consulta. Acesso direto sem comprovante mostra orientação genérica para o formulário. Não refletir dados de outro pedido em reenvios ou mensagens de coincidência.

### Operações internas e pré-condições

Cada escrita sobre registro existente envia a versão exibida na tela. O servidor verifica estado, versão, permissões e vínculos novamente ao gravar. IDs enviados não comprovam autorização nem pertencimento.

| Operação | Entrada específica proposta | Pré-condição e efeito |
| --- | --- | --- |
| Correção | Campos permitidos, `motivo`, `versao` | Somente pedido `enviada/em_analise`; preserva declaração anterior e autoria, recalcula alertas. Não edita identidade canônica silenciosamente. |
| Iniciar análise | `versao` | `enviada → em_analise`, com autor/instante. Reabrir tela não assume exclusividade de atendimento. |
| Contato | `canal`, `ocorrido_em`, `resultado`, `resumo`, `proxima_acao`, `pedidos_abordados[]`, `identidade_conferida`, `duvida_esclarecida`, versões dos pedidos | Canal WhatsApp/telefone/e-mail; resumo operacional. Tentativa/sem resposta/número incorreto não podem declarar dúvida esclarecida. Registrar relato do funcionário, sem alegar verificação automática do canal. |
| Conferência | Destinos tipados (`pedido`/`cadastro`), IDs, `contato_id`, `resultado`, `motivo`, versões | `pessoas_distintas` exige contato que esclareceu a coincidência; `aguardando_esclarecimento` não encerra alerta e admite tentativa sem resposta. Preservar os pares/campos examinados. |
| Resolver repetição | `acao=arquivar_selecionados|admitir_principal`, `pedidos_ids[]`, `principal_id` quando aplicável, `cadastro_referencia_id` quando houver, `contato_id`, `motivo`, versões, escolha de identidade nova/existente | Pedidos ativos, acesso a todos, contato que abrange o conjunto com identidade conferida e dúvida esclarecida, estado/versão atuais. Arquivar os selecionados ou admitir o escolhido e arquivar os demais numa operação única. |
| Admissão simples | `contato_id`, `motivo`, `versao`, escolha de identidade nova/existente | Pedido em análise, dados completos e identidade conferida. Proposta: exigir contato realizado também sem flag. Sem repetição pendente; grava autor/instante, identidade confirmada e `admitida`. Não cria doadora aprovada. |

Vínculo de identidade: a equipe escolhe explicitamente criar identificação ou usar cadastro existente autorizado após conferência. Validar que essa escolha corresponde ao contato e à resolução; não escolher pelo primeiro CPF/telefone encontrado. Dados divergentes não sobrescrevem a identidade existente; correção do cadastro confirmado será uma operação própria, fora deste recorte.

Quando a correspondência for com **cadastro já concluído**, não arquivar nem alterar esse cadastro pela ação de resolução. Proposta: após contato esclarecer que o novo pedido foi engano, permitir arquivar o pedido recebido, com referência ao cadastro existente e motivo. Se for uma nova necessidade de atendimento, registrar esclarecimento e manter pendente de encaminhamento; reabrir processo clínico permanece fora deste recorte. Não criar segundo cadastro confirmado automaticamente.

Generalização proposta dos exemplos de dois pedidos: resolução aceita o conjunto efetivamente esclarecido no contato, sem incluir automaticamente todos os resultados da flag. Para admitir um principal, ele pertence ao conjunto e os demais são arquivados; correspondências pendentes fora do conjunto precisam ser esclarecidas antes da admissão. Para arquivar, permitir dois ou mais pedidos repetidos, ou um pedido recebido com referência a cadastro já concluído. Pedidos de pessoas distintas não podem ser agrupados por telefone/nome compartilhado. Validar IDs únicos, contato abrangente e permissões para todos os alvos.

### Fila, alertas e views

Filtros GET propostos: `situacao=ativos|arquivados|todos`, `com_correspondencias=1`, `ordem=antigos|recentes`, `page`. Padrão: ativos, mais antigos primeiro, desempate por ID, 25 itens por página. Parâmetros têm valores permitidos definidos; não aceitar nomes arbitrários de coluna/SQL. Não colocar CPF, telefone ou e-mail em parâmetros de URL.

| View proposta | Dados permitidos ao frontend |
| --- | --- |
| `solicitacoes.create` | Opções do formulário, token da operação, erros e valores permitidos da própria tentativa. |
| `solicitacoes.recebida` | Mensagem neutra e comprovante temporário da própria operação, se disponível. |
| `interno.solicitacoes.index` | `pedidos` paginados com resumo administrativo, `filtros` normalizados, indicador agregado e ações autorizadas. |
| `interno.solicitacoes.create` | Formulário compartilhado e canais internos, sem permissões/estado editáveis. |
| `interno.solicitacoes.show` | `pedido`, `correspondencias` atuais autorizadas, `contatos`, `historicoAdministrativo`, `acoesPermitidas`, versões. |
| `interno.acompanhamentos.index/show` | Cadastros admitidos e identificação administrativa, origem, autoria da admissão e visita pendente. Sem relatório/anexos clínicos neste recorte. |

Correspondência é dado derivado, não campo editável. Comparar pedidos ativos e identidades de cadastros admitidos; excluir arquivados nos dois lados e não duplicar o pedido de origem como destino de seu próprio cadastro. Um nome igual sinaliza possível homônimo. Arquivamento/admissão/correção atualizam os indicadores afetados. Uma conferência de pessoas distintas preserva o indício como revisado; nova correspondência não herda automaticamente essa revisão.

### Respostas e mensagens propostas

| Resultado | Resposta web |
| --- | --- |
| Recebimento válido | Redirecionamento 302 para confirmação pública ou detalhe interno; mensagem `pedido-recebido`. |
| Contato/conferência/correção salva | 302 para detalhe interno; `contato-registrado`, `conferencia-registrada` ou `pedido-atualizado`. |
| Admissão/resolução válida | 302 para acompanhamento do principal ou fila de pedidos quando ambos arquivados; `cadastro-admitido` ou `pedidos-arquivados`. |
| Campos inválidos ou contato sem esclarecimento exigido | 302 de volta ao formulário/detalhe com erros por campo e valores permitidos; sem alteração parcial. Bags propostos: padrão para cadastro/correção, `contato`, `conferencia`, `resolucao` e `admissao` para ações na mesma tela. |
| Sem sessão na área interna | Redirecionar ao login. |
| Sem função de atendimento | 403; menu oculto não substitui a verificação. |
| Registro inexistente ou fora do escopo concedido | 404 sem revelar dados do alvo. |
| Estado/versão mudou durante a operação | 409 com HTML orientando recarregar e conferir; sem escrita parcial ou repetição automática da decisão. |
| Sessão/CSRF expirado | 419; orientar reabrir formulário, sem reenviar silenciosamente. |
| Limite de recebimento excedido | 429, mensagem neutra de tentar depois; sem indicar existência de CPF ou cadastro. |

Mensagem de admissão: “Cadastro concluído para acompanhamento. A aprovação para doar ainda depende das próximas etapas.” Arquivamento: “Pedidos arquivados e retirados da análise. O histórico foi preservado.” Erros de resolução devem dizer o requisito faltante, como “Registre o contato que esclareceu a repetição antes de concluir”.

### Consistência, repetição e verificação futura

Admissão e resolução são atômicas: validar todos os alvos, gravar mudanças/vínculos/autoria e atualizar a fonte dos indicadores sem resultado parcial. Em concorrência, apenas uma resolução vence; a outra encontra conflito. Uma retransmissão comprovada da mesma operação não cria novas admissões ou eventos. Novo pedido independente não é descartado só porque tem dados iguais.

Cobertura planejada, **não executada**: entrada pública/interna e validação cruzada dos telefones/WhatsApp; campos técnicos manipulados; permissão e acesso por registro; um alerta com vários motivos; arquivado excluído das comparações; contato sem resposta bloqueando resolução; admissão sem aprovação clínica; arquivamento de ambos e escolha do principal; conflito concorrente sem escrita parcial; revogação da função em sessão aberta. Usar dados fictícios e verificar persistência e resposta, não só status HTTP. SQLite não comprova travas/concorrência MySQL; verificar esse comportamento em banco de testes isolado, nunca recriar o banco local de trabalho.

Compatibilidade: o formulário novo não é o `/register` do Breeze. O cadastro de contas e as contas doadora existentes continuam no código atual; antes de disponibilizar o fluxo público, decidir a retirada do autocadastro Breeze da navegação/rotas e preservar dados existentes. Nenhuma alteração de autenticação foi feita nesta especificação. A exclusão física de conta atual também precisa ser compatibilizada com autoria histórica antes dos vínculos de domínio.
## Contas exclusivamente internas — decisão e implementação de 27/10/2026

O usuário determinou a retirada integral do acesso de doadoras e autorizou a remoção das contas antigas. **O frontend não deve criar cadastro/login/área de doadora.** A interessada utiliza `/doar` sem conta; solicitações e identidades do domínio permanecem independentes de `users`.

- `/register` GET/POST retorna 404; controller e view de autocadastro foram removidos. Login e recuperação/redefinição de senha aceitam exclusivamente contas internas. Sessões sem usuário interno válido são encerradas com 403 antes do acesso web.
- `GET /interno/funcionarios/novo` (`interno.funcionarios.create`) e `POST /interno/funcionarios` (`interno.funcionarios.store`) exigem sessão e `criar-funcionarios`. Admin tem a capacidade; funcionários precisam de concessão explícita, revogável e auditada pela administração.
- Entrada: nome e e-mail válidos/únicos. Servidor fixa `role=funcionario`, senha aleatória não divulgada e `criado_por_user_id`. Campos de papel, senha, autoria ou permissões enviados pelo cliente não são aplicados. Não há criação de admin nem concessão de capacidades por esse formulário.
- A pessoa define sua própria senha pela recuperação por e-mail já existente. O cadastro não envia automaticamente convite, não inicia sessão da pessoa criada e não troca a sessão do operador. Entrega real de e-mail depende da configuração operacional; não foi homologada nesta tarefa.
- A migration `2026_10_27_202742_restrict_users_to_internal_accounts` remove contas antigas com papel `doadora`, suas sessões de banco e tokens de recuperação; restringe o enum a `admin|funcionario`, sem padrão implícito, e acrescenta autoria da criação. Não altera interessadas, solicitações ou dados clínicos. FKs continuam ativas: vínculo inesperado impede a exclusão, sem apagar histórico em cascata. `down()` não restaura contas excluídas.
- Seeder de desenvolvimento passa a criar somente admin/funcionário; catálogo inclui a permissão nova sem concedê-la automaticamente. Referências anteriores deste documento a autocadastro ou perfil doadora são histórico superado por esta decisão.
