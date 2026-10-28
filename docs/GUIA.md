# Guia da documentação

A base de implementação foi descrita em 22/10/2026; Roadmap, Modelagem e Contratos receberam propostas de admissão em 23/10/2026. Em 24/10/2026, foram detalhados R5 e, após autorização do usuário, preparadas migrations do schema administrativo verificadas em SQLite em memória, sem aplicação ao MySQL local. Consulte Modelagem e Status para o recorte e os limites. O código e as migrations são evidências de implementação; ideias e arquivos antigos não comprovam funcionalidades disponíveis.

## Estados usados

Especificação em 28/10/2026: atendimento e relatório narrativo detalhados em Contratos e Modelagem. O usuário confirmou leitura separada de escrita/finalização, acesso automático do admin e continuidade/correção por qualquer profissional com escrita autorizada, preservando autoria e versões. R1–R3 estão acordadas; campos, persistência e detalhes de operação permanecem propostos. Nenhum código, migration ou dado foi alterado nesse recorte documental.

Ativação da agenda em 28/10/2026: quatro migrations aplicadas no desenvolvimento, lote 5; não há migrations pendentes. Dados anteriores preservados, sem concessões automáticas. Fluxo web e concorrência revalidados em MariaDB exclusivo (11 testes). Revisão visual pendente por ausência de navegador. Próximo recorte: detalhar atendimento/relatório e permissões profissionais antes de implementar.

Atualização local em 28/10/2026: migration de retirada das contas de doadora e catálogo de permissões aplicados com autorização. Confirmadas somente as duas contas internas existentes, sem concessões automáticas. As quatro migrations de agenda continuam pendentes. Evidência em [Status](backend/STATUS.md#aplicação-das-contas-internas--28102026); os relatos de conexão indisponível abaixo são históricos.

Decisão vigente de acesso em 27/10/2026: **não existem contas nem área autenticada de doadora**. O público envia solicitações sem conta; autenticação é exclusiva de `admin` e `funcionario`. Autocadastro foi removido. Administradores e funcionários com `criar-funcionarios` cadastram funcionários, sem conceder permissões nem criar administradores. A migration de retirada remove contas antigas e o valor `doadora` do enum atual; referências nas migrations anteriores e nas evidências históricas não autorizam recriar esse acesso. Consulte Contratos e Status para implementação e aplicação local.

Atualização em 27/10/2026: a agenda recebeu rotas e telas Blade de lista, contatos, confirmação, reagendamento, cancelamento, não realização e histórico, protegidas por `gerenciar-agenda`. A revisão visual e a ativação do schema/catálogo no desenvolvimento permanecem pendentes. Detalhes e resultados em [Status](backend/STATUS.md#fluxo-web-da-agenda--27102026); os registros abaixo preservam as etapas anteriores.

Atualização mais recente: operações transacionais de agenda e contatos após admissão implementados no backend. O usuário confirmou bloqueio de sobreposição do responsável. Rotas e telas ainda estão pendentes; consulte [Contratos](backend/CONTRATOS.md#operações-de-backend-implementadas-em-26102026) e Status. Nenhuma dessas entregas representa aprovação clínica.

Atualização posterior em 26/10/2026: models e autorização de agenda implementados, incluindo permissão independente e acumulável com atendimento. O fluxo web da agenda continua pendente; schema/catálogo não foram aplicados ao desenvolvimento nesta entrega. Consulte [Contratos](backend/CONTRATOS.md#contrato-técnico-proposto-da-agenda--26102026) e Status para o recorte efetivo.

Avanço posterior em 26/10/2026: schema de agenda preparado em quatro migrations e testes de integridade, com autorização do usuário, sem aplicação ao banco de desenvolvimento. Models, permissão de agenda e fluxo web continuam pendentes; evidências em [Status](backend/STATUS.md).

Atualização de 26/10/2026: o usuário relatou teste manual satisfatório do site após a entrega administrativa. O próximo recorte de agenda e relatório está em [especificação proposta](backend/CONTRATOS.md#próximo-recorte-agenda-e-relatório-de-visita--proposta-em-26102026), com decisões operacionais ainda pendentes. O relato manual e seus limites estão em Status; não substitui as validações pendentes descritas nas etapas anteriores.

Refinamento confirmado pelo usuário: visitas nos dois locais, preferencialmente no domicílio; permissões separadas acumuláveis por funcionário; relatório com rascunho e correções versionadas após finalizar. Contratos distingue esses acordos dos detalhes técnicos e atribuições profissionais ainda propostos.

Estado local mais recente em 24/10/2026: migrations administrativas e catálogo de permissões aplicados com autorização, preservando as três contas existentes e sem concessões automáticas. A revisão visual continua pendente por indisponibilidade de navegador nesta sessão. Veja [ativação local](backend/STATUS.md#ativação-local--24102026); os relatos anteriores preservam o histórico das etapas.

Atualização posterior em 24/10/2026: o schema também foi verificado em MariaDB isolado; models administrativos, Gates e proteção de autoria na exclusão foram implementados. Consulte [Contratos](backend/CONTRATOS.md#base-de-models-e-autorização-implementada-em-24102026) e [Status](backend/STATUS.md) para distinguir a base implementada dos fluxos/telas ainda pendentes.

| Estado | Significado |
| --- | --- |
| Proposto | Sugestão para discussão; não autoriza implementação nem representa aprovação da equipe. |
| Acordado | Diretriz confirmada, com origem identificada. Não implica que já foi implementada. |
| Implementado | Existe no código ou na configuração versionada, com referência à fonte. |
| Validado | Há uma verificação identificada, com data, ambiente e limites. Não equivale a homologação do produto. |

As instruções de documentação e limites de atuação solicitadas pelo usuário são **acordadas para esta entrega**. O primeiro fluxo e suas diretrizes estruturais estão **acordados conforme relato do usuário em 23/10/2026**, com origem registrada em [R1](backend/ROADMAP.md). Campos funcionais de abertura, alerta agregado, função de atendimento, WhatsApp, resolução de repetição após contato, exclusão de arquivados das comparações e admissão administrativa seguida de visita/relatório antes da solicitação de documentos/exames receberam definições adicionais do usuário, registradas no Roadmap. Tipos/limites, relações, estados técnicos, demais desfechos dos alertas, permissões técnicas, novas convenções e prioridades continuam **propostos para revisão**. Não há ata ou homologação do serviço consultada.

## Ordem de leitura

1. [Ambiente](AMBIENTE.md): preparar e executar a aplicação.
2. [Arquitetura](ARQUITETURA.md): entender backend, frontend e fluxo web.
3. [Padrões](PADROES.md): regras existentes, propostas e limites.
4. [Status do backend](backend/STATUS.md): implementação e evidências.
5. [Modelagem](backend/MODELAGEM.md): tabelas e pendências.
6. [Contratos](backend/CONTRATOS.md): integração Laravel e Blade.
7. [Roadmap](backend/ROADMAP.md): propostas futuras e dependências.
8. [Segurança](SEGURANCA.md): controles, reforços, testes pendentes e evidências por assunto.

Cada assunto tem um documento principal acima. Evite repetir instruções nos demais: use links.

Para análises ou correções de segurança, comece pelos IDs aplicáveis em [Segurança](SEGURANCA.md), confira suas fontes no código e atualize as evidências junto com a mudança. O checklist orienta a revisão e não autoriza, por si só, implementar todos os itens pendentes.

## Para novos integrantes e agentes

Comece pelo [README](../README.md). Agentes devem ler também [AGENTS.md](../AGENTS.md); [CLAUDE.md](../CLAUDE.md) remete às mesmas instruções. Confira a implementação antes de reutilizar uma afirmação documental.

Não confunda conta de acesso com cadastro de doadora do domínio. Recebimento, fila, detalhe, contato, conferência, arquivamento de repetidos e admissão administrativa têm implementação. O acompanhamento inicial mostra visita pendente, sem aprovação para doar. Consulte [Contratos](backend/CONTRATOS.md#atendimento-administrativo-implementado-em-26102026). As telas de apoio não definem a interface final; evidências e limites ficam em Status.

Mudanças de comportamento devem atualizar a documentação afetada **no mesmo pull request**. Registre o estado, a evidência e as limitações; não converta uma proposta em acordo sem decisão identificável.

[Adaptação para Bootstrap](APLICAR_BOOTSTRAP.md) registra uma adaptação anterior; [README_laravel.md](../README_laravel.md) é material complementar. Para instalação atual, a referência principal é [AMBIENTE.md](AMBIENTE.md).
