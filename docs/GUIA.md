# Guia da documentação

A base de implementação foi descrita em 22/10/2026; Roadmap, Modelagem e Contratos receberam propostas de admissão em 23/10/2026. Em 24/10/2026, foram detalhados R5 e, após autorização do usuário, preparadas migrations do schema administrativo verificadas em SQLite em memória, sem aplicação ao MySQL local. Consulte Modelagem e Status para o recorte e os limites. O código e as migrations são evidências de implementação; ideias e arquivos antigos não comprovam funcionalidades disponíveis.

## Estados usados

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

Não confunda conta de acesso com cadastro de doadora do domínio. O recebimento público/interno, a fila e o detalhe administrativo de solicitações já têm implementação; contato, arquivamento e admissão ainda não. Consulte [Contratos](backend/CONTRATOS.md#recebimento-e-fila-implementados-em-24102026). As telas de apoio não definem a interface final.

Mudanças de comportamento devem atualizar a documentação afetada **no mesmo pull request**. Registre o estado, a evidência e as limitações; não converta uma proposta em acordo sem decisão identificável.

[Adaptação para Bootstrap](APLICAR_BOOTSTRAP.md) registra uma adaptação anterior; [README_laravel.md](../README_laravel.md) é material complementar. Para instalação atual, a referência principal é [AMBIENTE.md](AMBIENTE.md).
