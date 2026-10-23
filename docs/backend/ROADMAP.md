# Roadmap do backend

Todas as etapas e prioridades abaixo são **propostas para revisão da equipe**. Não há prazo, escopo de domínio ou atribuição aprovados. Responsáveis permanecem **a definir**. A base implementada está em [Status](STATUS.md).

P0 indica pré-requisito de alinhamento; P1, base de implementação; P2, evolução após a base. A ordem definitiva depende dos fluxos reais do Banco de Leite.

| Etapa | Prioridade proposta | Dependências | Entrega e critério de conclusão propostos | Responsável |
| --- | --- | --- | --- | --- |
| R1 — Definir primeiro fluxo | P0 | Discussão com equipe e usuários do serviço. | Glossário, escopo do primeiro caso de uso, regras e aceite registrados como acordados com origem da decisão. Separar conta de acesso de entidade de domínio. | a definir |
| R2 — Revisar acesso e dados | P0 | R1 para permissões de domínio; base de autenticação já existe. | Matriz de acesso por ação/registro, política de cadastro, verificação de e-mail e exclusão/retenção discutidas e aprovadas; tratamento das rotas de teste definido. | a definir |
| R3 — Consolidar ambiente e qualidade | P1 | Decisão de versões e ambiente de teste da equipe. | Scripts de instalação alinhados ao MySQL sem regenerar chave existente; estratégia do seeder definida; testes de papéis e integração MySQL com evidências; CI e revisão acordadas. | a definir |
| R4 — Modelar o primeiro fluxo | P1 | R1 e R2. | Campos, relações, restrições, estados e preservação de histórico aprovados em [Modelagem](MODELAGEM.md); plano de migrations revisado. | a definir |
| R5 — Acordar integração | P1 | R1 e R4; alinhamento com frontend. | Contrato do primeiro fluxo preenchido com entradas, view/resposta, mensagens e permissão, usando o [modelo](CONTRATOS.md); tecnologia de integração escolhida conforme necessidade. | a definir |
| R6 — Implementar primeiro fluxo | P1 | R2 a R5. | Código, migrations e testes de sucesso, erro e acesso; interface integrada ao contrato; documentação atualizada no mesmo PR; aceite registrado. | a definir |
| R7 — Avaliar migração do legado | P2 | R4; acesso e inventário do legado ainda não verificados. | Mapeamento de origem/destino, qualidade de dados, ensaio e restauração definidos e revisados; evidência de reconciliação antes de migrar dados reais. Pode subir de prioridade se R1 depender do legado. | a definir |
| R8 — Planejar operação | P2 | Fluxo implementado e decisões de segurança. | Hospedagem, backups e teste de restauração, observabilidade, e-mail e gestão de acesso definidos; homologação antes de produção. | a definir |

## Decisões humanas necessárias

Confirmar primeiro fluxo e vocabulário; regras e perfis autorizados; coleta mínima e retenção de dados; critérios de aceite; prioridade do legado; versões compartilhadas; processo de revisão/CI; necessidade de integrações externas; responsáveis e sequência de entrega.

A arquitetura atual permite contratos HTML/Blade. Uma API REST só entra no roadmap mediante necessidade e decisão explícitas. Estes documentos não autorizam implementar nenhum módulo.
