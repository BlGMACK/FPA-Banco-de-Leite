# Guia da documentação

A base de implementação foi descrita em 22/10/2026; Roadmap, Modelagem e Contratos receberam propostas de admissão em 23/10/2026. O código e as migrations são evidências de implementação; ideias e arquivos antigos não comprovam funcionalidades disponíveis.

## Estados usados

| Estado | Significado |
| --- | --- |
| Proposto | Sugestão para discussão; não autoriza implementação nem representa aprovação da equipe. |
| Acordado | Diretriz confirmada, com origem identificada. Não implica que já foi implementada. |
| Implementado | Existe no código ou na configuração versionada, com referência à fonte. |
| Validado | Há uma verificação identificada, com data, ambiente e limites. Não equivale a homologação do produto. |

As instruções de documentação e limites de atuação solicitadas pelo usuário são **acordadas para esta entrega**. O primeiro fluxo e suas diretrizes estruturais estão **acordados conforme relato do usuário em 23/10/2026**, com origem registrada em [R1](backend/ROADMAP.md). Campos, relações, estados, permissões detalhadas, novas convenções e prioridades continuam **propostos para revisão**. Não há ata ou homologação do serviço consultada.

## Ordem de leitura

1. [Ambiente](AMBIENTE.md): preparar e executar a aplicação.
2. [Arquitetura](ARQUITETURA.md): entender backend, frontend e fluxo web.
3. [Padrões](PADROES.md): regras existentes, propostas e limites.
4. [Status do backend](backend/STATUS.md): implementação e evidências.
5. [Modelagem](backend/MODELAGEM.md): tabelas e pendências.
6. [Contratos](backend/CONTRATOS.md): integração Laravel e Blade.
7. [Roadmap](backend/ROADMAP.md): propostas futuras e dependências.

Cada assunto tem um documento principal acima. Evite repetir instruções nos demais: use links.

## Para novos integrantes e agentes

Comece pelo [README](../README.md). Agentes devem ler também [AGENTS.md](../AGENTS.md); [CLAUDE.md](../CLAUDE.md) remete às mesmas instruções. Confira a implementação antes de reutilizar uma afirmação documental.

Não confunda conta de acesso com cadastro de doadora do domínio. Não há módulos de negócio implementados no código analisado. As telas de apoio não definem a interface final.

Mudanças de comportamento devem atualizar a documentação afetada **no mesmo pull request**. Registre o estado, a evidência e as limitações; não converta uma proposta em acordo sem decisão identificável.

[Adaptação para Bootstrap](APLICAR_BOOTSTRAP.md) registra uma adaptação anterior; [README_laravel.md](../README_laravel.md) é material complementar. Para instalação atual, a referência principal é [AMBIENTE.md](AMBIENTE.md).
