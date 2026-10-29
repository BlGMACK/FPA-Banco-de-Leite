# FPA — Banco de Leite Humano

Aplicação web Laravel e Blade do Banco de Leite Humano de Marília. Possui solicitação pública sem conta, atendimento e admissão administrativa, agenda de visitas e relatórios de visita com rascunho e correções versionadas. O acesso autenticado é exclusivo de funcionários e administradores; não existe autocadastro nem conta de doadora. Documentos/exames, avaliação de saúde e habilitação para doar ainda não estão implementados. Consulte o [estado atual](docs/backend/STATUS.md#estado-atual--29102026) para funcionalidades, ativação local e limites de validação.

A arquitetura atual usa PHP, Laravel, Blade, Bootstrap e MySQL/MariaDB. As telas de apoio ao desenvolvimento não representam a interface final.

## Documentação

Comece pelo [Guia e ordem de leitura](docs/GUIA.md).

- [Ambiente](docs/AMBIENTE.md): requisitos, instalação, configuração local, execução e testes.
- [Arquitetura](docs/ARQUITETURA.md): stack, pastas e responsabilidades de backend e frontend.
- [Padrões](docs/PADROES.md): orientações existentes, propostas para revisão e limites de atuação.
- [Status do backend](docs/backend/STATUS.md): estado atual, evidências e pendências.
- [Roadmap do backend](docs/backend/ROADMAP.md): etapas propostas e dependências.
- [Modelagem](docs/backend/MODELAGEM.md): schema atual e questões em aberto.
- [Contratos](docs/backend/CONTRATOS.md): integração Laravel/Blade e modelo para futuros fluxos.
- [Segurança](docs/SEGURANCA.md): controles, verificações pendentes e evidências por assunto.

Agentes devem iniciar por [AGENTS.md](AGENTS.md); [CLAUDE.md](CLAUDE.md) aponta para a mesma referência.

Mudanças de comportamento devem atualizar a documentação no mesmo pull request. Convenções novas e etapas futuras estão identificadas como propostas, sujeitas à revisão da equipe.

## Referências complementares

[Adaptação para Bootstrap](docs/APLICAR_BOOTSTRAP.md) mantém o registro da adaptação anterior; [README_laravel.md](README_laravel.md) contém material complementar do framework. O procedimento atual de instalação está centralizado em [Ambiente](docs/AMBIENTE.md).

O README anterior citava `main` como branch de integração e `Banco-de-leite_old` como referência do legado. A existência e o conteúdo das branches remotas não foram verificados nesta revisão; consulte a equipe antes de depender do legado.
