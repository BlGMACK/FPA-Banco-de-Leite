# Roadmap do backend

As prioridades e entregas técnicas abaixo são **propostas para revisão da equipe**. O primeiro fluxo e suas diretrizes estruturais foram acordados conforme relato do usuário em 23/10/2026, registrado abaixo. Campos, relações, estados e permissões detalhadas continuam propostos. Não há prazos aprovados. Responsáveis permanecem **a definir**. A base implementada está em [Status](STATUS.md).

P0 indica pré-requisito de alinhamento; P1, base de implementação; P2, evolução após a base. A ordem definitiva depende dos fluxos reais do Banco de Leite.

| Etapa | Prioridade proposta | Dependências | Entrega e critério de conclusão propostos | Responsável |
| --- | --- | --- | --- | --- |
| R1 — Definir primeiro fluxo | P0 | Discussão com equipe e usuários do serviço. | Glossário, escopo do primeiro caso de uso, regras e aceite registrados como acordados com origem da decisão. Separar conta de acesso de entidade de domínio. | a definir |
| R2 — Revisar acesso e dados | P0 | R1 para permissões de domínio; base de autenticação já existe. | Matriz de acesso por ação/registro, política de cadastro, verificação de e-mail e exclusão/retenção discutidas e aprovadas; tratamento das rotas de teste definido. | a definir |
| R3 — Consolidar ambiente e qualidade | P1 | Decisão de versões e ambiente de teste da equipe. | Scripts de instalação alinhados ao MySQL sem regenerar chave existente; estratégia do seeder definida; testes de papéis e integração MySQL com evidências; CI e revisão acordadas. | a definir |
| R4 — Modelar o primeiro fluxo | P1 | R1 e R2. | Campos, relações, restrições, estados e preservação de histórico aprovados em [Modelagem](MODELAGEM.md); plano de migrations revisado. | a definir |
| R5 — Acordar integração | P1 | R1 e R4; alinhamento com frontend. | Contrato do primeiro fluxo preenchido com entradas, view/resposta, mensagens e permissão, usando o [modelo](CONTRATOS.md); tecnologia de integração escolhida conforme necessidade. | a definir |
| R6 — Implementar primeiro fluxo | P1 | R2 a R5. | Código, migrations e testes de sucesso, erro e acesso; interface integrada ao contrato; documentação atualizada no mesmo PR; aceite registrado. | a definir |
| R7 — Avaliar migração do legado | P2 | R4; schema da branch local consultado, acesso ao banco e inventário de dados ainda não verificados. | Mapeamento de origem/destino, qualidade de dados, ensaio e restauração definidos e revisados; evidência de reconciliação antes de migrar dados reais. Pode subir de prioridade se R1 depender do legado. | a definir |
| R8 — Planejar operação | P2 | Fluxo implementado e decisões de segurança. | Hospedagem, backups e teste de restauração, observabilidade, e-mail e gestão de acesso definidos; homologação antes de produção. | a definir |

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

**Solicitação → análise pela equipe → confirmação do agendamento → atendimento e triagem → avaliação de saúde → decisão registrada → habilitação para doações e coletas.**

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
| D1 | Conta obrigatória antes da solicitação? | Se aceitar visitante, definir comprovação de identidade e acesso posterior. Protocolo isolado não deve conceder acesso. |
| D2 | Equipe pode registrar pedidos por telefone ou presencialmente? | Confirmar canais e representação da interessada. |
| D3 | Campos obrigatórios e momento de CPF/CNS. | Revisar dicionário proposto com o serviço. |
| D4 | Endereço completo ou conjunto mínimo inicial. | Confirmar conforme modalidade de atendimento. |
| D5 | Quem preenche, revisa e aprova cada avaliação? | Confirmar atribuições e se uma pessoa pode preencher e aprovar a mesma avaliação. |
| D6 | Aptidão, documentos, exames, validade e reavaliação. | Confirmar com o Banco de Leite e referências disponibilizadas; nenhum critério clínico foi inventado. |
| D7 | Duplicidade e retorno após recusa. | Definir identificadores e solicitações simultâneas permitidas. |
| D8 | Cancelamento, ausência e reagendamento. | Definir atores, prazos e efeitos sobre a solicitação. |
| D9 | Criar doadora na aprovação ou atualizar cadastro existente? | Recomendação e alternativa em Modelagem; escolha ainda pendente. |
| D10 | Correções, exclusão de conta e retenção. | Histórico preservado é diretriz; mecanismo, prazo e acesso precisam de definição. |
| D11 | Resultado exibido e comunicação à interessada. | Confirmar conteúdo e responsáveis; não expor justificativa técnica por padrão. |
| D12 | Aceite final do serviço. | Identificar participantes e confirmar propostas e cenários; não presumir aprovação coletiva. |

### Critérios de aceite — propostos, não executados

| ID | Cenário | Resultado esperado |
| --- | --- | --- |
| A1 | Conta criada ou solicitação enviada, inclusive com campos técnicos manipulados. | Nenhuma aprovação automática; papel administrativo e campos técnicos não são aplicados pelo formulário. |
| A2 | Solicitação informa data desejada. | Não cria agendamento confirmado; confirmação posterior registra equipe, instante e horário combinado. |
| A3 | Falta campo obrigatório acordado. | Erro no campo e nenhuma submissão parcialmente salva. |
| A4 | Interessada troca URL ou identificador para acessar outra pessoa. | Acesso negado no servidor sem exposição de dados. |
| A5 | Funcionário ou admin sem atribuição clínica tenta ler dados restritos ou decidir. | Acesso negado conforme matriz proposta; papel não implica atribuição clínica. |
| A6 | Avaliação exigida ainda está pendente. | Aprovação bloqueada; complementação não equivale a resultado desfavorável. |
| A7 | Profissional autorizado registra decisão. | Responsável, instante e avaliação preservados; cadeia pertence à mesma solicitação e pessoa. |
| A8 | Aprovação enviada duas vezes ou simultaneamente. | Uma decisão final por avaliação e uma doadora por pessoa; falha não deixa habilitação parcial. |
| A9 | Solicitação recusada ou avaliação desfavorável. | Histórico preservado, sem habilitação; justificativas têm acesso controlado. |
| A10 | Reagendamento ou correção de avaliação concluída. | Registro anterior preservado e ligado ao sucessor; decisão anterior mantém seu fundamento. |
| A11 | Correção de dados básicos. | Vínculos mantidos e histórico suficiente para recuperar o contexto anterior. |
| A12 | Retorno de pessoa anteriormente recusada. | Histórico preservado; novo fluxo depende de D6/D7, sem aprovação automática. |

R1 tem fluxo e diretrizes estruturais acordados conforme relato do usuário; regras detalhadas e aceite permanecem pendentes. R2/R4 têm propostas em [Contratos](CONTRATOS.md) e [Modelagem](MODELAGEM.md). R5 ainda precisa fixar rotas, views e respostas. R6 depende de R2–R5, incluindo qualidade em R3.

Verificação: inspeção estática dos documentos, código atual e schema local legado. Sem execução do fluxo, validação clínica ou conteúdo de PDF consultado.
