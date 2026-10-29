# Guia da documentação

## Situação em 29/10/2026

O primeiro fluxo (solicitação até habilitação para doar) está implementado até o **relatório de visita**: solicitação pública sem conta, fila com alertas de correspondência, atendimento e admissão administrativa, agenda de visitas e relatório narrativo com rascunho, finalização e correções versionadas. As 20 migrations estão aplicadas no banco de desenvolvimento e a suíte automatizada passa (302 testes, 4 ignorados por exigirem MariaDB).

Ainda não existem: documentos/exames, avaliação de saúde, decisão e habilitação; interface de concessão de permissões; correção de dados do pedido; coleta, estoque e distribuição. O fluxo completo foi exercitado em navegador automatizado em 29/10/2026, sem defeitos funcionais; falta a avaliação de usabilidade pela equipe.

O retrato completo, com fontes e evidências, está no [Estado atual do Status](backend/STATUS.md#estado-atual--29102026). O código e as migrations são as evidências de implementação; ideias, propostas e arquivos antigos não comprovam funcionalidades disponíveis.

### Decisão vigente de acesso

**Não existem contas nem área autenticada de doadora.** O público envia solicitações sem conta; autenticação é exclusiva de `admin` e `funcionario`. Autocadastro foi removido. Administradores e funcionários com `criar-funcionarios` cadastram funcionários, sem conceder permissões nem criar administradores. Permissões são separadas e acumuláveis por funcionário; o admin tem acesso automático. Referências ao papel doadora em migrations antigas e evidências históricas não autorizam recriar esse acesso. Detalhes em [Contratos](backend/CONTRATOS.md#contas-exclusivamente-internas--decisão-e-implementação-de-27102026).

### Linha do tempo resumida

| Data | Entrega |
| --- | --- |
| 22/10/2026 | Levantamento da base Laravel/Breeze adaptada a Bootstrap e da documentação. |
| 23/10/2026 | Primeiro fluxo e diretrizes acordados a partir do guia transmitido pelo usuário ([R1](backend/ROADMAP.md#r1--solicitação-até-habilitação-para-doar)). |
| 24/10/2026 | Schema administrativo, models, Gates, recebimento público e fila; aplicação local. |
| 26/10/2026 | Atendimento administrativo e admissão; base e operações transacionais da agenda. |
| 27/10/2026 | Telas da agenda; contas exclusivamente internas e cadastro de funcionários. |
| 28/10/2026 | Ativação local da agenda; especificação, schema, models e operações dos relatórios de visita. |
| 29/10/2026 | Ativação local dos relatórios e telas Blade de relatório; build de assets aprovado. |

Cada etapa, com comandos, resultados e limites, está no [histórico do Status](backend/STATUS.md#histórico-de-verificações).

## Estados usados

| Estado | Significado |
| --- | --- |
| Proposto | Sugestão para discussão; não autoriza implementação nem representa aprovação da equipe. |
| Acordado | Diretriz confirmada, com origem identificada. Não implica que já foi implementada. |
| Implementado | Existe no código ou na configuração versionada, com referência à fonte. |
| Validado | Há uma verificação identificada, com data, ambiente e limites. Não equivale a homologação do produto. |

As instruções de documentação e limites de atuação solicitadas pelo usuário são **acordadas**. O primeiro fluxo e suas diretrizes estruturais estão **acordados conforme relato do usuário em 23/10/2026**, com origem registrada em [R1](backend/ROADMAP.md). Campos de abertura, alerta agregado, função de atendimento, WhatsApp, resolução de repetição após contato, exclusão de arquivados das comparações, visitas no domicílio ou no Banco, permissões acumuláveis, bloqueio de sobreposição na agenda e o ciclo do relatório (rascunho, finalização, correções versionadas, leitura separada da escrita, acesso do admin e continuidade por qualquer profissional autorizado) receberam definições do usuário, registradas no Roadmap e em Contratos. Demais desfechos, atribuições profissionais, critérios clínicos, novas convenções e prioridades continuam **propostos para revisão**. Não há ata ou homologação do serviço consultada.

## Ordem de leitura

1. [Ambiente](AMBIENTE.md): preparar e executar a aplicação.
2. [Arquitetura](ARQUITETURA.md): entender backend, frontend e fluxo web.
3. [Padrões](PADROES.md): regras existentes, propostas e limites.
4. [Status do backend](backend/STATUS.md): estado atual, evidências e pendências.
5. [Modelagem](backend/MODELAGEM.md): tabelas e pendências.
6. [Contratos](backend/CONTRATOS.md): integração Laravel e Blade.
7. [Roadmap](backend/ROADMAP.md): propostas futuras e dependências.
8. [Segurança](SEGURANCA.md): controles, reforços, testes pendentes e evidências por assunto.

Cada assunto tem um documento principal acima. Evite repetir instruções nos demais: use links. Ao concluir uma etapa, atualize o **Estado atual** do Status e acrescente o registro datado ao histórico, em vez de empilhar avisos no topo dos demais documentos.

Para análises ou correções de segurança, comece pelos IDs aplicáveis em [Segurança](SEGURANCA.md), confira suas fontes no código e atualize as evidências junto com a mudança. O checklist orienta a revisão e não autoriza, por si só, implementar todos os itens pendentes.

## Para novos integrantes e agentes

Comece pelo [README](../README.md). Agentes devem ler também [AGENTS.md](../AGENTS.md); [CLAUDE.md](../CLAUDE.md) remete às mesmas instruções. Confira a implementação antes de reutilizar uma afirmação documental.

Não confunda conta de acesso com cadastro de doadora do domínio. Admissão administrativa, visita realizada e relatório finalizado não aprovam ninguém para doar; não há aprovação clínica no sistema. As telas de apoio não definem a interface final; evidências e limites ficam em Status.

Mudanças de comportamento devem atualizar a documentação afetada **no mesmo pull request**. Registre o estado, a evidência e as limitações; não converta uma proposta em acordo sem decisão identificável.

[Adaptação para Bootstrap](APLICAR_BOOTSTRAP.md) registra uma adaptação anterior; [README_laravel.md](../README_laravel.md) é material complementar. Para instalação atual, a referência principal é [AMBIENTE.md](AMBIENTE.md).
