# Roadmap do backend

As prioridades e entregas técnicas abaixo são **propostas para revisão da equipe**. O primeiro fluxo e suas diretrizes estruturais foram acordados conforme relato do usuário em 23/10/2026, registrado abaixo. Campos, relações, estados e permissões detalhadas continuam propostos onde não houver acordo identificado. Não há prazos aprovados. Responsáveis permanecem **a definir**. A base implementada está no [estado atual do Status](STATUS.md#estado-atual--29102026).

## Situação em 29/10/2026

**Atualização de 05/11/2026:** o primeiro fluxo está implementado de ponta a ponta. Depois da análise do relatório (31/10/2026), vieram exames e documentos e seus anexos (01/11/2026) e a decisão de habilitação com a tabela `doadoras` (02/11/2026), todos aplicados no desenvolvimento ([Status](STATUS.md#estado-atual--29102026)). A visita complementar durante *Em aguardo* foi decidida e implementada em 05/11/2026 ([decisão](#avaliação-após-visita-exames-e-documentos--acordado-em-29102026)). A correção de dados do pedido foi decidida e implementada no mesmo dia: `atender-solicitacoes` corrige; antes da admissão, todos os dados; depois, só contatos e endereço do cadastro ([Contratos](CONTRATOS.md#correção-de-dados-do-pedido--implementada-em-05112026)). Restam no R1: retenção dos anexos, avaliação de usabilidade e aceite do serviço (D12). Coleta, recebimento de leite, processamento, estoque e distribuição não têm fluxo acordado. Esta posição não autoriza implementação. O parágrafo seguinte registra a situação de 29/10/2026.

O primeiro fluxo está implementado até o relatório de visita: recebimento, atendimento e admissão administrativa, agenda e relatório narrativo versionado, todos aplicados no desenvolvimento. **Próximo passo:** o roteiro das telas de relatório passou em navegador automatizado ([registro](STATUS.md#teste-das-telas-em-navegador--29102026)); as sugestões de usabilidade foram aplicadas e a tela de gestão de permissões foi entregue; falta a equipe avaliar as telas. Em 29/10/2026 o usuário definiu as regras da [desativação de funcionários](#desativação-de-funcionários--acordado-em-29102026) e a estrutura da [avaliação após visita, exames e documentos](#avaliação-após-visita-exames-e-documentos--acordado-em-29102026). A desativação foi implementada no mesmo dia e sua migration foi aplicada no desenvolvimento em 31/10/2026. Em 31/10/2026 o usuário fechou as regras da análise do relatório, que foi implementada e aplicada no desenvolvimento no mesmo dia ([decisões](#avaliação-após-visita-exames-e-documentos--acordado-em-29102026)); exames e habilitação ainda dependem da lista real e da decisão D9. Correção de dados do pedido continua sem definição. Esta posição não autoriza implementação.

P0 indica pré-requisito de alinhamento; P1, base de implementação; P2, evolução após a base. A ordem definitiva depende dos fluxos reais do Banco de Leite.

| Etapa | Prioridade proposta | Dependências | Entrega e critério de conclusão propostos | Situação em 29/10/2026 | Responsável |
| --- | --- | --- | --- | --- | --- |
| R1 — Definir primeiro fluxo | P0 | Discussão com equipe e usuários do serviço. | Glossário, escopo do primeiro caso de uso, regras e aceite registrados como acordados com origem da decisão. Separar conta de acesso de entidade de domínio. | Fluxo e diretrizes acordados pelo usuário; regras até o relatório definidas. Etapas clínicas (D6, D9) e aceite do serviço (D12) pendentes. | a definir |
| R2 — Revisar acesso e dados | P0 | R1 para permissões de domínio; base de autenticação já existe. | Matriz de acesso por ação/registro, política de cadastro, verificação de e-mail e exclusão/retenção discutidas e aprovadas; tratamento das rotas de teste definido. | Parcial. Contas exclusivamente internas, permissões por capacidade e tela de gestão de permissões implementadas e testadas; rotas de teste removidas. Desativação de funcionários implementada em 29/10/2026. Mensagens do framework em pt-BR desde 31/10/2026. Verificação de e-mail e retenção pendentes. | a definir |
| R3 — Consolidar ambiente e qualidade | P1 | Decisão de versões e ambiente de teste da equipe. | Scripts de instalação alinhados ao MySQL sem regenerar chave existente; estratégia do seeder definida; testes de papéis e integração MySQL com evidências; CI e revisão acordadas. | Parcial. Testes em MariaDB exclusivo com guarda de isolamento e matriz de acesso por módulo. Scripts Composer e `DatabaseSeeder` alinhados em 05/11/2026; versões compartilhadas e CI pendentes. | a definir |
| R4 — Modelar o primeiro fluxo | P1 | R1 e R2. | Campos, relações, restrições, estados e preservação de histórico aprovados em [Modelagem](MODELAGEM.md); plano de migrations revisado. | Implementado até a habilitação (31 migrations, todas aplicadas no desenvolvimento em 02/11/2026), incluindo avaliação, exames, anexos e `doadoras`. | a definir |
| R5 — Acordar integração | P1 | R1 e R4; alinhamento com frontend. | Contrato do primeiro fluxo preenchido com entradas, view/resposta, mensagens e permissão, usando o [modelo](CONTRATOS.md); tecnologia de integração escolhida conforme necessidade. | Contratos Blade implementados até o relatório. Sem API REST. | a definir |
| R6 — Implementar primeiro fluxo | P1 | R2 a R5. | Código, migrations e testes de sucesso, erro e acesso; interface integrada ao contrato; documentação atualizada no mesmo PR; aceite registrado. | Implementado até a decisão de habilitação, com testes (05/11/2026). Visita complementar implementada em 05/11/2026. Correção de dados do pedido implementada em 05/11/2026. Retenção dos anexos e das correções implementada em 05/11/2026, com prazo ainda a definir pela equipe. Faltam revisão visual e aceite registrado. | a definir |
| R7 — Avaliar migração do legado | P2 | R4; schema da branch local consultado, acesso ao banco e inventário de dados ainda não verificados. | Mapeamento de origem/destino, qualidade de dados, ensaio e restauração definidos e revisados; evidência de reconciliação antes de migrar dados reais. Pode subir de prioridade se R1 depender do legado. | Não iniciado. | a definir |
| R8 — Planejar operação | P2 | Fluxo implementado e decisões de segurança. | Hospedagem, backups e teste de restauração, observabilidade, e-mail e gestão de acesso definidos; homologação antes de produção. | Não iniciado. | a definir |

## Decisões humanas necessárias

Detalhar o vocabulário do fluxo escolhido; regras e perfis autorizados; coleta mínima e retenção de dados; critérios de aceite; prioridade do legado; versões compartilhadas; processo de revisão/CI; necessidade de integrações externas; responsáveis e sequência de entrega.

A arquitetura atual permite contratos HTML/Blade. Uma API REST só entra no roadmap mediante necessidade e decisão explícitas. Estes documentos não autorizam implementar nenhum módulo.

## R1 — Solicitação até habilitação para doar

Atualizado em 23/10/2026. O usuário escolheu “Cadastro de doadoras” e transmitiu um guia elaborado após discussão com o grupo. Esse guia substitui a hipótese anterior de cadastro direto pela equipe. A origem do acordo é o relato do usuário nesta conversa; não há ata ou homologação do serviço consultada.

### Fontes e estados

| Código | Origem | Uso |
| --- | --- | --- |
| G | Guia transmitido pelo usuário em 23/10/2026. | Diretrizes expressamente afirmadas são acordadas conforme seu relato. Propostas e pendências do próprio guia continuam abertas. |
| L | Schema local legado identificado em [Modelagem](MODELAGEM.md). | Referência histórica, sem comprovar regra atual ou implementação Laravel. |
| P | Nova proposta técnica desta revisão. | Depende de revisão antes de programar. |
| PDF | Não disponível. | Nenhuma regra foi atribuída a PDF. |

### Fluxo e diretrizes acordadas — G

**Solicitação → análise e contato pela equipe → cadastro administrativamente concluído → contato para agendar visita → visita e relatório → análise das informações → solicitação de documentos/exames quando indicada → avaliação de saúde → decisão registrada → habilitação para doações e coletas.**

A interessada recebe orientações e registra interesse, possivelmente a partir de um link na página da prefeitura. Isso é contexto pretendido, não uma integração implementada. Coletas, processamento, estoque e distribuição ficam em fluxos posteriores; aqui se registra a habilitação que permitirá a participação.

- Conta de acesso, solicitação e doadora aprovada representam momentos diferentes.
- Criar conta, receber papel doadora ou enviar solicitação não aprova a pessoa.
- Data desejada é preferência; a equipe registra o atendimento combinado.
- O formulário público não escolhe perfil administrativo, resultados de exames ou aprovação.
- A interessada acessa somente seus próprios dados e solicitações; funcionários atuam conforme permissões.
- Triagem e avaliação de saúde permanecem distintas; profissionais autorizados tratam dados de saúde e decisões técnicas.
- A decisão registra responsável, data e avaliação de origem.
- Recusa e avaliação desfavorável preservam histórico, com acesso controlado às justificativas.
- Dados básicos são relacionados entre etapas para evitar cadastros independentes e divergentes.

### Refinamento de entrada e acesso — relato de 23/10/2026

- **Preferência do usuário, ainda proposta (D1/D11):** formulário sem conta para a interessada e acompanhamento pelo WhatsApp, evitando portal de consulta de status. O usuário explicitou que ainda não há decisão concreta coletiva. A proposta detalhada passa a usar essa direção como hipótese de trabalho.
- **Confirmado pelo usuário (D2):** a equipe deve conseguir registrar solicitações recebidas por telefone ou presencialmente, sem exigir conta da interessada; registrar canal e funcionário autor.
- **Proposta do usuário (D5):** contas internas com permissões configuráveis por funcionário, permitindo acumular funções; administrador com alcance amplo. Cargos reais, poderes clínicos e concessão de permissões continuam a definir.
- **Revisado pelo usuário (D7):** receber pedidos com indícios de duplicidade e mostrar um alerta único por pedido, detalhando campos e outros pedidos/cadastros relacionados. Isso substitui a proposta anterior de bloquear uma segunda solicitação ativa. A resolução continua humana.
- **Proposta técnica:** WhatsApp operado manualmente pela equipe neste primeiro recorte, com registro mínimo dos contatos no sistema. Não há aprovação de API, robô ou envio automático.
- **Pendência adicional:** “mandar dados para atualizar” precisa distinguir primeiro pedido de correção de cadastro existente. Recomenda-se que correções recebidas sem autenticação sejam conferidas pela equipe antes de alterar dados.

O princípio anterior de acesso apenas aos próprios dados continua aplicável se houver futuramente uma área de consulta. Na hipótese atual, não há consulta pública de cadastro ou status por CPF/telefone.

### Definições da entrada confirmadas em 23/10/2026

Origem: respostas do usuário sobre campos, alertas, cargo de atendimento e contato, incluindo confirmação posterior sobre a escolha do número de WhatsApp.

- Nome completo, CPF, e-mail, município, bairro e endereço completo na abertura; telefone fixo e/ou celular, com pelo menos um preenchido; campo amplo de detalhes adicionais opcional.
- A pessoa escolhe um dos números informados como WhatsApp e declara disponibilidade. Sem essa indicação/declaração, não é possível abrir pedido. A equipe verifica no contato; não há verificação automática de conta WhatsApp.
- Os pedidos são listados em ordem de criação, com destaque e filtro de alerta. A direção da ordenação é proposta em Contratos.
- Um alerta reúne todas as correspondências do pedido e identifica campos e outros pedidos/cadastros já completos; a equipe decide o encaminhamento. Não bloquear automaticamente pelo simples dado repetido.
- Tratamento de pedidos agrupado em uma função de atendimento, concedida a um ou mais funcionários com contas individuais.
- WhatsApp é requisito inicial. E-mail e telefone podem ser usados como canais secundários a critério do funcionário; não substituem silenciosamente o requisito de abertura.

Detalhes técnicos, limites de campos, normalização e resolução dos alertas permanecem **propostos**. Requisitos do usuário não significam implementação ou homologação pelo serviço.

### Glossário de trabalho

| Termo | Significado |
| --- | --- |
| Conta | Registro de autenticação e papel; já existe como `users`. |
| Interessada | Pessoa identificada no processo, sem pressupor aprovação; denominação proposta. |
| Solicitação | Manifestação de interesse e preferência de atendimento. |
| Agendamento | Atendimento combinado pela equipe. |
| Atendimento | Registro do que efetivamente ocorreu na visita ou atendimento. |
| Triagem | Informações coletadas para subsidiar a avaliação. |
| Avaliação de saúde | Etapa técnica, distinta da triagem; critérios clínicos pendentes. |
| Decisão | Resultado profissional registrado com autoria, data e fundamento. |
| Doadora aprovada | Pessoa habilitada por decisão autorizada; persistência ainda proposta. |

### Pendências para revisão

| ID | Decisão pendente | Encaminhamento |
| --- | --- | --- |
| D1 | Confirmar entrada pública sem conta. | **Resolvido e implementado (27/10/2026):** não existem contas de doadora; o pedido é público e sem conta. Permanecem a definir a verificação no contato e o tratamento de correções; CPF/protocolo não concedem acesso. |
| D2 | Registro interno sem conta da interessada. | Confirmado pelo usuário: permitir telefone/presencial, registrando canal e funcionário; campos mínimos ainda em D3. |
| D3 | Campos iniciais definidos pelo usuário. | Nome completo, CPF, e-mail, município/bairro, endereço completo e fixo e/ou celular (ao menos um); detalhes adicionais opcionais. CNS e preferência de data não entram como obrigatórios; momento a definir. Tipos/limites em Modelagem são propostos. |
| D4 | Endereço completo na abertura, confirmado pelo usuário. | Decomposição e limites propostos em Modelagem; complemento opcional. |
| D5 | Atendimento dos pedidos agrupado em uma função. | Confirmado pelo usuário e implementado como permissão `atender-solicitacoes`, acumulável com agenda e relatórios; admin com acesso automático (confirmado em 28/10/2026). Atribuições clínicas e habilitações profissionais continuam pendentes. |
| D6 | Aptidão, documentos, exames, validade e reavaliação. | Sequência confirmada: somente solicitar documentos/exames após visita e análise das informações, quando houver indicação. **Estrutura acordada em 29/10/2026** ([avaliação e exames](#avaliação-após-visita-exames-e-documentos--acordado-em-29102026)): permissões, catálogo fixo, estados, anexos, validade e regra de avanço. Lista real de exames, prazos por tipo e desfechos da decisão ainda dependem do serviço. |
| D7 | Alertas de correspondência e resolução humana. | Confirmado: contato que esclarece a repetição, escolha humana de qual pedido avança e qual arquiva, ou arquivamento de ambos com motivo. Comparar somente pedidos ativos e cadastros administrativamente concluídos; arquivados não geram flags. Detalhes técnicos/reabertura continuam propostos ou pendentes. |
| D8 | Cancelamento, ausência e reagendamento. | **Implementado na agenda:** quem tem `gerenciar-agenda` reagenda, cancela ou registra não realização com motivo, preservando a admissão e o histórico. Prazos, limites de tentativas e efeitos de política sobre a solicitação continuam a definir. |
| D9 | Criar doadora na aprovação ou atualizar cadastro existente? | Recomendação e alternativa em Modelagem; escolha ainda pendente. |
| D10 | Correções, exclusão de conta e retenção. | Histórico preservado é diretriz. **Desativação de funcionários acordada em 29/10/2026** ([regras](#desativação-de-funcionários--acordado-em-29102026)). **Correção de pedidos implementada em 05/11/2026.** **Retenção acordada em 05/11/2026:** prazo configurável, contado da decisão de habilitação (anexos) ou da correção (valores corrigidos); descarte só por administrador, na tela “Retenção”, mantendo o registro ([contrato](CONTRATOS.md#retenção-dos-anexos-e-das-correções--implementada-em-05112026)). O número de anos e a retenção dos demais dados continuam a definir. |
| D11 | WhatsApp exigido para abertura. | Confirmado pelo usuário: indicar um dos telefones e declarar disponibilidade; equipe confere no contato. E-mail/telefone são secundários a critério do funcionário. Operação manual proposta; sem garantia automática de número ativo. |
| D12 | Aceite final do serviço. | Identificar participantes e confirmar propostas e cenários; não presumir aprovação coletiva. |

### Critérios de aceite — propostos, não executados

| ID | Cenário | Resultado esperado |
| --- | --- | --- |
| A1 | Conta criada ou solicitação enviada, inclusive com campos técnicos manipulados. | Nenhuma aprovação automática; papel administrativo e campos técnicos não são aplicados pelo formulário. |
| A2 | Solicitação informa data desejada. | Não cria agendamento confirmado; confirmação posterior registra equipe, instante e horário combinado. |
| A3 | Falta campo obrigatório acordado. | Erro no campo e nenhuma submissão parcialmente salva. |
| A4 | Visitante consulta CPF/telefone/ID ou envia correção para cadastro existente. | Sem consulta pública ou alteração automática; dados existentes preservados e divergências encaminhadas à equipe. |
| A5 | Funcionário sem permissão tenta ler dados restritos, decidir ou conceder acesso a si mesmo. | Acesso negado no servidor; alcance administrativo e atos clínicos definidos em D5. |
| A6 | Avaliação exigida ainda está pendente. | Aprovação bloqueada; complementação não equivale a resultado desfavorável. |
| A7 | Profissional autorizado registra decisão. | Responsável, instante e avaliação preservados; cadeia pertence à mesma solicitação e pessoa. |
| A8 | Aprovação enviada duas vezes ou simultaneamente. | Uma decisão final por avaliação e uma doadora por pessoa; falha não deixa habilitação parcial. |
| A9 | Solicitação recusada ou avaliação desfavorável. | Histórico preservado, sem habilitação; justificativas têm acesso controlado. |
| A10 | Reagendamento ou correção de avaliação concluída. | Registro anterior preservado e ligado ao sucessor; decisão anterior mantém seu fundamento. |
| A11 | Correção de dados básicos. | Vínculos mantidos e histórico suficiente para recuperar o contexto anterior. |
| A12 | Retorno de pessoa anteriormente recusada. | Histórico preservado; novo fluxo depende de D6/D7, sem aprovação automática. |
| A13 | Dois pedidos contêm dados repetidos, inclusive com cadastro já completo. | Ambos ficam disponíveis para análise, com uma flag por pedido detalhando campos e registros relacionados; sem mesclagem, atualização ou recusa automática e sem exposição pública. |
| A14 | Contato por WhatsApp falha ou não tem resposta. | Registro da tentativa e próxima ação interna; envio de mensagem não confirma leitura, identidade ou agenda. |
| A15 | Permissão de funcionário é concedida ou revogada. | Autor/data registrados; próximas operações refletem a mudança, inclusive em sessão já aberta. |
| A16 | Só fixo, só celular ou ambos preenchidos. | Aceitar as três combinações com os demais campos válidos, WhatsApp selecionado entre números informados e declaração de disponibilidade; ausência de ambos os telefones bloqueia o envio. |
| A17 | Não há indicação/declaração de WhatsApp. | Formulário informa o requisito e não abre pedido, inclusive no registro interno; contato secundário não contorna a regra. |
| A18 | Pedido coincide por CPF, e-mail e telefone com vários registros. | Um único alerta agrega todos os motivos e destinos, sem contar três flags para o mesmo pedido. |
| A19 | Novo pedido coincide com pedido ativo ou cadastro administrativamente concluído. | Alerta detalha apenas alvos elegíveis e dados administrativos; se coincidir só com arquivados, não gera flag. Histórico anterior continua acessível separadamente. |
| A20 | Funcionário tenta confirmar repetição após apenas enviar mensagem, sem esclarecer a dúvida com a interessada. | Resolução bloqueada; tentativa de contato não equivale a confirmação. |
| A21 | Após contato que esclareceu a repetição, funcionário decide arquivar os dois pedidos e informa motivo. | Ambos saem da fila de análise, mas permanecem consultáveis no histórico com vínculos, autor, instante, motivo e contato que fundamentou a ação. |
| A22 | Contato esclarece repetição e a interessada deseja prosseguir. | Funcionário escolhe o pedido principal; admissão administrativa e arquivamento do repetido ficam vinculados, com autoria e fundamento, sem aprovação clínica. |
| A23 | Único destino de correspondência é arquivado. | Recalcular os pedidos afetados e retirar a flag atual; manter somente evidência histórica da resolução. |
| A24 | Cadastro é administrativamente concluído. | Surge na área de acompanhamento com visita pendente, sem habilitação para coleta nem exigência automática de documentos/exames. |
| A25 | Funcionário registra relatório de visita e recebe documentos posteriormente solicitados. | Relatório e anexos mantêm autoria e vínculos; recebimento de anexos não conclui avaliação automaticamente. |

### Resolução após contato — definição do usuário em 23/10/2026

O funcionário precisa entrar em contato com a interessada e esclarecer se os pedidos realmente são os mesmos antes de confirmar a repetição. O usuário admitiu arquivar os dois pedidos, retirando-os da análise e explicando o motivo. Arquivamento preserva os dados e a evidência histórica da análise; deixa de participar das correspondências atuais e não significa exclusão nem reprovação clínica.

**Esclarecimento confirmado pelo usuário:** “cadastro concluído” significa permissão administrativa para iniciar o acompanhamento, ainda sem aprovação para doar. O funcionário escolhe qual pedido avança e qual arquiva. O arquivado sai das comparações; apenas pedidos ativos e cadastros administrativamente concluídos participam dos alertas.

Após essa admissão, o cadastro aparece em uma área de acompanhamento com etapas pendentes. A equipe entra em contato para agendar visita presencial, conversar, tirar dúvidas e levantar a situação da interessada e as condições de armazenamento/retirada. A preferência atual do usuário é registrar a visita em um relatório narrativo amplo, podendo rever esse formato.

Documentos e exames só são solicitados depois da análise dessas informações, quando indicados. A interessada os envia pelo canal de contato telefônico e o funcionário os anexa ao cadastro; a proposta operacional usa WhatsApp, sem upload público ou integração automática. Receber um arquivo não equivale a avaliá-lo ou aprovar a doadora. Os dados básicos já definidos para o pedido inicial continuam exigidos; o adiamento diz respeito à documentação e aos exames da avaliação.

R1 tem fluxo e diretrizes estruturais acordados conforme relato do usuário; regras detalhadas e aceite permanecem pendentes. R2/R4 têm propostas em [Contratos](CONTRATOS.md) e [Modelagem](MODELAGEM.md). Em 24/10/2026, R5 recebeu [contrato técnico do primeiro recorte](CONTRATOS.md#r5--contrato-técnico-do-primeiro-recorte), com rotas, views, entradas, respostas, autorização e consistência. É proposta para revisão, não implementação ou homologação. R6 depende de R2–R5, incluindo qualidade em R3.

### Preparação da implementação — proposta de sequência em 24/10/2026

> Registro histórico: os parágrafos abaixo foram acrescentados a cada entrega, do mais recente ao mais antigo, e descrevem a situação na data. A posição vigente está em [Situação em 29/10/2026](#situação-em-29102026).

**Fluxo web da agenda em 27/10/2026:** endpoints e telas operacionais implementados, incluindo filtros, admitidas sem reserva, contatos e histórico. Acesso independente para `gerenciar-agenda`, sem dados de análise inicial ou clínica. Próximos passos: ativação autorizada do schema/catálogo no desenvolvimento e revisão visual/manual; depois, detalhar o recorte de atendimento/relatório e permissões profissionais antes de programá-lo. Evidências e limitações em Status.

**Operações da agenda em 26/10/2026:** contatos após admissão, confirmação, reagendamento, cancelamento e não realização implementados no backend, com validação, Gate, versão, operação única e transação. Usuário confirmou bloqueio de sobreposição do mesmo funcionário; horários distintos no mesmo dia permanecem permitidos e deslocamento é manual. A integração às telas foi entregue na atualização acima. Aplicação de migrations/catálogo ao desenvolvimento continua separada.

**Base de models/autorização da agenda em 26/10/2026:** implementados três models, factories, relações, catálogo/Gate `gerenciar-agenda` e extensão da concessão/revogação auditada. Testes verificam acesso separado/acumulado e revogação. Não aplicados schema/catálogo ao desenvolvimento; não há interface de agenda. Próximo recorte: contatos após admissão e operações transacionais de confirmação/reagendamento/cancelamento, com testes e telas conforme Contratos. Capacidade/sobreposição e atribuições clínicas continuam com as pendências já registradas.

**Schema de agenda preparado em 26/10/2026:** o usuário autorizou a próxima entrega; quatro migrations aditivas e `AgendaSchemaTest` implementados. Preservadas migrations anteriores e banco de desenvolvimento. Ver [Modelagem](MODELAGEM.md#desenho-físico-proposto-para-agenda--26102026) e [Status](STATUS.md). Próxima etapa: models, catálogo/Gate/concessões de agenda e testes da separação/acúmulo de permissões, antes dos endpoints e telas de agenda. As referências abaixo a schema apenas proposto descrevem as etapas anteriores.

**Detalhamento técnico posterior em 26/10/2026:** [persistência de agenda](MODELAGEM.md#desenho-físico-proposto-para-agenda--26102026) e [contrato de autorização/rotas](CONTRATOS.md#contrato-técnico-proposto-da-agenda--26102026) preparados como propostas. Reutilizar contatos com contexto explícito; acrescentar agenda, reserva atual e histórico; separar acesso `gerenciar-agenda` de atendimento e relatório. Próxima entrega de código proposta: migrations aditivas e testes em banco exclusivo, depois catálogo/Gates e fluxo web. Nada desse schema foi criado ou aplicado nesta etapa.

**Refinamento em 26/10/2026:** após relato de teste manual satisfatório e autorização para avançar, foi preparada a [proposta de agenda e relatório](CONTRATOS.md#próximo-recorte-agenda-e-relatório-de-visita--proposta-em-26102026). Ela detalha estados, vínculos, permissões e testes; local da visita, alcance da agenda e ciclo do relatório aguardam resposta do usuário. Não há código ou migrations desse novo recorte. A adaptação dos contatos para acompanhamentos admitidos é uma dependência identificada no código atual.

**Respostas posteriores em 26/10/2026:** o usuário confirmou visita preferencialmente domiciliar, também possível no Banco; funções com permissões separadas e acumuláveis pela mesma pessoa; relatório com rascunho e novas versões após finalizar. Esses três pontos deixam de ser pendências. Permanecem duração/capacidade/encaixes e atribuições profissionais específicas. Próximo trabalho: desenho técnico da agenda e contatos após admissão, preservando a separação entre acesso administrativo e relatório.

**Atualização em 26/10/2026:** após autorização do usuário, foram implementados contato, conferência, arquivamento de repetidos, admissão administrativa e consulta de acompanhamento com visita pendente. Ver [contrato efetivo](CONTRATOS.md#atendimento-administrativo-implementado-em-26102026) e [Status](STATUS.md). A próxima etapa é validar a interface e fechar o contrato de agendamento/visita e relatório narrativo antes de programar o fluxo clínico. Correção dos dados de pedidos também permanece pendente; não considerar todo R6 concluído. Os parágrafos abaixo preservam a sequência histórica.

Atualização posterior: a ativação local do schema e do catálogo foi concluída com autorização, sem concessões automáticas a funcionários. A revisão visual permanece pendente por indisponibilidade de navegador; veja [Status](STATUS.md#ativação-local--24102026). A próxima etapa funcional permanece contato e decisões administrativas.

Atualização do recorte de recebimento/fila: formulário público e interno, confirmação, fila e detalhe com correspondências foram implementados com testes. Ver [contrato efetivo](CONTRATOS.md#recebimento-e-fila-implementados-em-24102026). Próxima etapa funcional: contato e decisões administrativas fundamentadas (conferência, arquivamento e admissão), mantendo os requisitos de transação/versão do contrato. A aplicação no banco local, revisão visual e homologação continuam separadas da implementação.

**Avanço autorizado nesta interação:** o usuário solicitou preparar o schema/migrations e verificar em banco isolado, sem aplicação ao banco local. Foram criadas seis migrations e testes de integridade: [schema físico](MODELAGEM.md#recorte-administrativo-preparado-em-24102026) e [evidências](STATUS.md#verificações-de-24102026--schema-administrativo). Este avanço entrega a persistência preparada, não a implementação completa de R6; autorização nas ações, modelos, fluxos e telas ainda são próximos trabalhos.

Atualização de 24/10/2026: o usuário autorizou e foram implementados os models, Gates e operações de concessão/revogação, com tratamento da exclusão de contas vinculadas. O [contrato implementado](CONTRATOS.md#base-de-models-e-autorização-implementada-em-24102026) delimita essa entrega; evidências em Status. O próximo recorte é recebimento/fila e integração da autorização nas rotas, sem considerar R6 concluído.

1. Consolidar o schema do recorte administrativo: pedidos, identidade confirmada, contatos, conferências/resoluções, histórico e concessão da função de atendimento. Rever exclusão de contas para preservar autoria; não criar tabelas clínicas antes de seus requisitos.
2. Implementar a base de persistência e autorização com testes; revisar migrations antes de aplicá-las, preservando o banco local.
3. Entregar recebimento público/interno, confirmação neutra e fila de pedidos com alertas calculados somente sobre registros elegíveis.
4. Entregar contato, conferência, arquivamento e admissão administrativa com transações, controle de concorrência e visão inicial de acompanhamento.
5. Validar os cenários do contrato, comportamento relevante em MySQL isolado e interface responsiva; registrar evidências e limitações. Atualizar Status somente quando houver implementação/verificação real.

Visita, relatório, documentos/exames e decisão clínica continuam na sequência funcional, mas fora desta primeira entrega. Pendências de cargos clínicos não impedem especificar a entrada administrativa; a implantação dessa entrada ainda exige autorização por função, proteção da autoria, definição operacional dos limites de recebimento e revisão do contrato.

Verificação: inspeção estática dos documentos, código atual e schema local legado. Sem execução do fluxo, validação clínica ou conteúdo de PDF consultado.
## Desativação de funcionários — acordado em 29/10/2026

Origem: respostas do usuário nesta data. **Implementado no mesmo dia**, após aprovação do desenho técnico ([Status](STATUS.md#desativação-de-funcionários--29102026)); a migration foi aplicada em `banco_de_leite_dev` em 31/10/2026. A proposta técnica abaixo foi a aprovada.

- Somente o admin desativa e reativa contas de funcionário. *Suposição informada ao usuário, sem objeção até o momento:* admin não desativa outro admin.
- A desativação bloqueia login e acesso imediatamente. As concessões ficam **suspensas**, não revogadas: ao reativar, o funcionário volta com o mesmo acesso.
- A desativação é **bloqueada** enquanto o funcionário for responsável por visita confirmada e ainda futura; antes, a equipe reagenda para outra pessoa ou cancela.
- Reativação permitida. Desativação e reativação exigem motivo e ficam registradas com autor e data.
- **Proposta técnica (aprovada e implementada):** marcar desativação na conta (ex.: `desativado_em`) com histórico próprio; `EnsureInternalAccount` e os Gates passam a negar contas desativadas; listas de responsáveis da agenda e de relatórios deixam de oferecê-las; a tela fica na área “Permissões”.

## Avaliação após visita, exames e documentos — acordado em 29/10/2026

Origem: respostas do usuário nesta data, que detalham D6 sem alterar a sequência de 23/10/2026. A **análise do relatório** foi implementada em 31/10/2026 ([Contratos](CONTRATOS.md#análise-do-relatório-avaliação--implementada-em-31102026)); os itens de exame e documento (etapa 1) e os anexos (etapa 2) foram implementados em 01/11/2026; a decisão de habilitação e a tabela `doadoras` (etapa 3), em 02/11/2026 ([Contratos](CONTRATOS.md#decisão-de-habilitação-e-doadoras-etapa-3--implementada-em-02112026)).

**Decisões do usuário em 31/10/2026** (análise do relatório):
- Desenho técnico aprovado, com uma permissão própria só de leitura da avaliação (`consultar-avaliacoes`), além de `avaliar-doadoras`.
- A avaliação é criada quando a visita é concluída, isto é, na primeira publicação do relatório.
- O relatório **não pode mais ser corrigido** depois de uma decisão definitiva da análise (*Liberar para exames* ou *Não apta*); *Em aguardo* ainda permite corrigir. Fundamento do usuário: o relatório e as informações anteriores servem à decisão; depois de analisados, não há lógica em editá-los. Para rever uma aprovação, o caminho será uma **revogação** da habilitação (com motivo, por revisão dos dados ou outra razão), a desenhar com a etapa de habilitação.
- *Em aguardo* termina somente quando um funcionário registra nova decisão, depois de reagendamento, espera de exames, revisão de documentos ou outro motivo. Sem prazo automático.

**Decisões do usuário em 01/11/2026** (exames, documentos e habilitação):
- **D9:** a doadora habilitada será registrada numa **tabela `doadoras`** criada na aprovação, em vez da tabela `habilitacoes` recomendada no desenho técnico. O desenho da tabela (vínculo com a interessada, dados copiados ou referenciados, suspensão, encerramento e nova habilitação) será detalhado na etapa 3.
- **Catálogo provisório:** começar com “Exame de sangue” (validade de 30 dias) e “Outro exame” (nome livre); a lista real entra depois por seeder versionado, sem tela de edição.
- **Anexos:** PDF, JPEG e PNG até 10 MB.
- **Entrega por etapas**, cada uma com testes, revisão e PR próprios: (1) itens de exame e documento, com catálogo, estados, validade e prorrogação — **implementada em 01/11/2026** ([Contratos](CONTRATOS.md#exames-e-documentos-etapa-1--implementada-em-01112026)); (2) anexos — **implementada em 01/11/2026**, sem descarte automático até a definição da retenção ([Contratos](CONTRATOS.md#anexos-dos-exames-etapa-2--implementada-em-01112026)); (3) decisão de habilitação e tabela `doadoras` — **implementada em 02/11/2026**.

**Decisões do usuário em 02/11/2026** (etapa 3):
- `doadoras` **referencia** a interessada já conferida e guarda só tipo, vigência e estado, sem copiar dados pessoais; uma pessoa pode ter várias habilitações ao longo do tempo, uma vigente por vez.
- *Habilitada temporariamente* exige **data-limite e condição**; ao vencer, o sistema só sinaliza, e a equipe renova, converte em definitiva ou encerra.
- A decisão e as ações posteriores (suspender, retomar, encerrar, revogar) usam `avaliar-doadoras`.
- A indicação de **plano de saúde** entra agora e é obrigatória na decisão de habilitação.
- **Catálogo definido:** o único exame do catálogo é o **exame de sangue**, com validade de **30 dias** contados da coleta. Continuam o documento CNS e a opção *Outro exame*. O catálogo deixa de ser provisório; não há mudança no sistema.

**Pendência criada por essas decisões:** “reagendamento” durante o aguardo exige nova visita no mesmo acompanhamento, que a agenda bloqueava após a visita realizada. **Decisão do usuário em 05/11/2026:** a nova visita gera novo relatório que **complementa a avaliação existente**. Implementada no mesmo dia ([Contratos](CONTRATOS.md#visita-complementar-durante-em-aguardo--implementada-em-05112026)).

**Fluxo.** Relatório de visita finalizado → **análise do relatório** por profissional autorizado → se as avaliações anteriores forem consideradas válidas, libera a etapa de **exames e documentos** (exames só são pedidos com essa indicação, como a próxima camada de verificação) → acompanhamento de cada item → decisão de habilitação.

**Permissões (três, separadas e acumuláveis).** Nomes técnicos propostos:
- *Consultar exames:* somente leitura dos itens e anexos, para quem não deve criar ou alterar exames.
- *Registrar exames:* solicitar itens, anexar arquivos e atualizar estados.
- *Avaliar doadoras:* analisar o relatório, liberar a etapa de exames e registrar a decisão. Concedida a profissionais de saúde, separada da escrita de relatórios.

**Catálogo e estados.**
- Lista **fixa** e relativamente ampla de tipos de exame e documento, mantida no sistema, sem tela de edição, para manter o uso simples. Inclui **“Outro exame”**: o funcionário informa nome e detalhes para um exame específico daquela doadora, com o mesmo estado e anexo dos demais.
- Todo item tem **estado obrigatório** por doadora, nunca em branco. Começa em **Não solicitado**. Ciclo: Não solicitado → Solicitado → Recebido → Aceito, ou Recebido → Precisa complementar → Solicitado.
- **Regra de avanço:** a doadora só passa às etapas seguintes quando todos os itens **solicitados** estiverem **Aceitos**, houver ao menos um item solicitado e nenhum estiver vencido. Itens “Não solicitado” não bloqueiam.

**Anexos.** Arquivos de exames (fotos e PDFs recebidos pelo WhatsApp) ficam guardados no sistema de forma segura e limitada: armazenamento privado, consulta a qualquer momento por quem tem permissão e nenhum acesso público. Requisitos técnicos em [SEG-12](../SEGURANCA.md#segurança-dos-módulos-e-da-operação).

**Validade (alerta, não bloqueio automático).**
- A validade conta da **data de realização/coleta**, informada obrigatoriamente ao marcar um exame como Recebido.
- Prazo padrão por tipo, inicialmente **um mês** para todos, até a equipe definir prazos próprios.
- Ao vencer, o item recebe sinalização e oferece duas ações: **pedir novo exame** ou **prorrogar por mais um mês com motivo**, no máximo **duas prorrogações**; depois, apenas novo exame. Tudo fica no histórico.

**Documentos de identificação** (na mesma etapa, como itens com estado):
- **CNS:** número com validação dos dígitos e registro de quem conferiu e quando, **sem guardar imagem** do cartão (minimização de dados sensíveis).
- **Plano de saúde:** apenas a indicação de que possui ou não possui.

**Desfechos.**
- **Análise do relatório:** *Liberar para exames*, *Em aguardo* (estado de espera explícito, para quando ainda não é possível decidir) ou *Não apta* (encerra o acompanhamento, com motivo e histórico preservado).
- **Decisão final**, possível somente quando a regra de avanço for cumprida: *Habilitada para doar*, *Habilitada temporariamente* (com prazo ou condição registrada pelo profissional) ou *Não habilitada* (com motivo e acesso controlado à justificativa).
- **Depois de habilitada:** a habilitação pode ser **suspensa** (temporária, com retorno possível) ou **encerrada** (definitiva), ambas com motivo e histórico.
- **Nova tentativa:** quem foi considerada não apta ou não habilitada pode enviar **novo pedido**. A equipe vê o histórico anterior no alerta de correspondência, sem aprovação nem recusa automáticas.

**Pendências.** ~~Lista real de exames e prazos~~ (definida em 02/11/2026: só o exame de sangue, 30 dias); o que encerra o estado *Em aguardo* (prazo, nova visita ou nova análise); o que define o fim de uma habilitação temporária; criação ou atualização do registro de doadora aprovada (D9; recomendação técnica no [desenho proposto](MODELAGEM.md#desenho-físico-proposto-para-avaliação-exames-e-documentos--29102026)); formatos e tamanho máximo de arquivo; ~~retenção dos anexos~~ (mecanismo implementado em 05/11/2026; prazo a definir pela equipe).

## Atualização de acesso — 27/10/2026

O usuário confirmou retirada do autocadastro e de todas as contas de doadora. Acesso interno exclusivo de admin/funcionário; criação de funcionários por admin ou funcionário com permissão específica. Implementados formulário interno, autoria e recuperação de senha para primeira definição, sem conceder permissões automaticamente. Ainda não há interface de concessão/revogação ou desativação de funcionários; isso requer recorte próprio. Entrega real de e-mail e revisão visual permanecem pendentes. A condição anterior de decidir sobre a permanência do Breeze público foi resolvida: `/register` foi removido.

## Recorte de atendimento e relatório — 28/10/2026

> Registro histórico do recorte, do mais recente ao mais antigo. Situação vigente em [Situação em 29/10/2026](#situação-em-29102026).

**Preparação da revisão manual em 29/10/2026:** integração do layout/assets conferida e build aprovado. Roteiro em Status; navegador indisponível e resultado manual da equipe ainda aguardado. A etapa de revisão das telas não está encerrada, e este registro não autoriza implementar documentos/exames sem definir suas regras.

**Interface entregue em 29/10/2026:** rotas/telas Blade de lista, realização, rascunho, publicação, correção e histórico, com testes HTTP em SQLite e MariaDB exclusivo. Próximo passo: revisar manualmente as telas, responsividade e interação Alpine, ajustando a usabilidade com a equipe. Documentos/exames ainda exigem definição funcional; não introduzir critérios clínicos ou uploads a partir deste roadmap.

**Ativação concluída em 29/10/2026:** cinco migrations e catálogo aplicados no desenvolvimento com autorização, preservando registros e sem concessões automáticas. Próximo recorte: interface Blade de relatórios, com rotas protegidas, testes de formulário/CSRF/escape e revisão manual. O backend e a persistência estão disponíveis; o fluxo de relatório ainda não está acessível por telas.

**Avanço posterior autorizado:** permissões e operações de realização, rascunho, finalização/correção implementadas; agenda integrada ao estado realizado e ao bloqueio de novo agendamento. Concorrência e rollback transacional verificados em MariaDB exclusivo. Próximo recorte: ativação local das migrations/catálogo e interface Blade própria do relatório, com testes de autorização/CSRF/escape e revisão manual. Ativação e telas ainda não foram executadas; referências a permissões/operações como próximo passo abaixo registram a sequência anterior.

Após ativação local da agenda, o usuário autorizou trabalhar na etapa de definição do atendimento e relatório. [Contrato detalhado](CONTRATOS.md#atendimento-e-relatório-narrativo--especificação-de-28102026) e [persistência proposta](MODELAGEM.md#persistência-proposta-para-atendimento-e-relatório--28102026) preparados sem implementar módulo ou alterar banco. Narrativa ampla, rascunho e versões após finalizar já são acordos; campos físicos e operações agora descritos são propostas.

R1–R3 confirmadas pelo usuário em 28/10/2026: leitura separada de escrita/finalização; administrador com acesso automático; qualquer profissional autorizado para escrita pode continuar/corrigir relatório de outro autor, com autoria e versões preservadas. Não inferir habilitações profissionais ou cargos reais. Realização com data efetiva, uma visita por agendamento, texto simples, imutabilidade e tratamento da fila após visita estão propostos para revisão.

R1–R3 fechadas e contrato atualizado. Após autorizações explícitas, migrations, models, factories e testes de integridade/relações foram implementados; evidências em [Status](STATUS.md). Próximo recorte: permissões de relatório, seguido de operações transacionais, telas/testes de integração e ativação local autorizada/revisão manual. A construção posterior depende de autorização do recorte, não apenas desta posição no roadmap. Exames, anexos, decisão clínica, retornos e encaixes continuam fora dessa sequência inicial.
