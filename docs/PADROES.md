# Padrões e limites

## Instruções existentes e acordos desta entrega

As orientações técnicas existentes estão em [AGENTS.md](../AGENTS.md). Para esta entrega, foi solicitado alterar apenas documentação, preservar instruções pertinentes e não fazer commit ou push. Novas convenções abaixo são **propostas para revisão da equipe**, sem aprovação coletiva presumida.

Mudanças de comportamento devem atualizar a documentação correspondente **no mesmo pull request**, conforme a solicitação desta padronização. Consulte o [Guia](GUIA.md) para escolher o documento principal e os estados de decisão.

## Código: observado e orientação existente

- **Implementado:** namespaces PSR-4, controllers e models no padrão Laravel; views Blade reutilizam layouts e componentes.
- **Implementado:** validação mista, em Form Requests (login/perfil) e controllers (cadastro/senha). Não há evidência de decisão por uma abordagem exclusiva.
- **Orientação existente:** nomes descritivos, tipos e retornos explícitos, chaves em estruturas de controle, reutilização de componentes e respeito às convenções próximas ao arquivo.
- **Orientação existente:** novas mudanças de schema em migrations; evitar editar migrations já aplicadas.
- **Orientação existente:** Pint para PHP alterado, testes proporcionais ao comportamento modificado e uso das skills pertinentes.
- **Proposto:** definir com a equipe quando extrair Form Requests, serviços e regras compartilhadas, sem criar camadas por antecipação.

## Branches, commits e revisão

O README anterior orientava branches a partir de `main` e integração por pull request, citando `Banco-de-leite_old` como referência histórica. Essa é uma orientação documental preexistente; branches remotas, proteção de branch e configurações do serviço Git não foram verificadas.

**Propostas para revisão:**

- Manter trabalho em branches curtas ligadas a uma tarefa; exemplos de nomes: `docs/guia`, `feat/nome-do-fluxo`, `fix/descricao`. Não há convenção de nomes comprovada no histórico consultado.
- Fazer commits pequenos, com mensagem que descreva a mudança e sua finalidade. Formato obrigatório, incluindo eventual uso de Conventional Commits, depende de decisão.
- Solicitar revisão de ao menos outro integrante antes da integração. Quantidade de aprovações, revisores, permissões de merge e regras de CI estão a definir.
- No PR, registrar comportamento anterior e novo, impacto em dados/permissões, evidências de verificação e documentos atualizados. Para esta tarefa, não executar commit/push.

## Validação e critérios de conclusão

**Orientação existente:** testar mudanças de lógica com cobertura útil, executar os testes afetados, usar Pint quando houver mudança PHP e compilar assets quando afetados. Os comandos de referência ficam em [Ambiente](AMBIENTE.md).

**Proposta de critério de conclusão:** escopo revisado, permissões e falhas relevantes cobertas, testes aplicáveis aprovados, efeitos em MySQL verificados quando houver persistência específica, documentação atualizada e limitações declaradas. Para interface, acrescentar revisão visual e de responsividade. Para documentação, conferir fontes, estados e links.

Implementação não equivale a validação. Registre comando, data, ambiente e resultado; testes com fakes não validam serviços externos. Sem execução, escreva “não executado”, com motivo.

## Permissões e dados

O comportamento atual está em [Contratos](backend/CONTRATOS.md); não existe um CRUD administrativo de papéis. Novas permissões por módulo e acesso por registro dependem de discussão.

**Orientações existentes:** não versionar `.env`, dependências instaladas, credenciais, dados pessoais ou registros reais de saúde; usar dados fictícios. Cada integrante mantém seu banco local; migrations compartilham estrutura, não registros.

**Limites para agentes, acordados nesta entrega:** respeitar o escopo autorizado; não converter roadmap em implementação; não alterar dependências, banco, configuração ou código em tarefa documental; não executar ações destrutivas nem publicar alterações sem autorização. Em tarefas futuras, aplicar a autorização específica recebida e documentar pendências sem inventar responsáveis.

**Propostas pendentes:** política de proteção de dados, retenção, auditoria, backups, restauração e homologação antes de dados reais. Não há comprovação de que esses processos estejam implementados.
