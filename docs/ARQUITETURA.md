# Arquitetura

## Estado implementado

A aplicação é um monólito web Laravel com páginas renderizadas por Blade e autenticação por sessão. Não há rotas de API de negócio no repositório analisado; a preferência por JSON em certos erros em [bootstrap/app.php](../bootstrap/app.php) não cria uma API.

| Parte | Evidência atual |
| --- | --- |
| Backend | PHP, Laravel 13, Eloquent; [composer.json](../composer.json) e [composer.lock](../composer.lock). O lock consultado fixa Laravel 13.34.0. |
| Autenticação | Fluxos derivados do Breeze; controllers em [Auth](../app/Http/Controllers/Auth), guard web em [config/auth.php](../config/auth.php). |
| Persistência | MySQL no [.env.example](../.env.example); estrutura definida pelas migrations. |
| Frontend | Blade, Bootstrap 5, Alpine e Popper; [package.json](../package.json). |
| Assets | Vite 8 e plugin Laravel; [vite.config.js](../vite.config.js). Versões exatas no [lock npm](../package-lock.json). |
| Testes e ferramentas | PHPUnit, Pint e Boost nas dependências de desenvolvimento. Disponibilidade não equivale a uso em CI. |

## Organização e responsabilidades

| Caminho | Responsabilidade observada |
| --- | --- |
| [routes/web.php](../routes/web.php) | Solicitação pública sem conta, painel/perfil internos, atendimento, agenda e criação autorizada de funcionários. Rotas de demonstração retiradas. |
| [routes/auth.php](../routes/auth.php) | Sessão, senha e verificação de e-mail para contas internas. Sem autocadastro. |
| [routes/console.php](../routes/console.php) | Comando de exemplo `inspire`; não há agendamento de negócio nesse arquivo. |
| [bootstrap/app.php](../bootstrap/app.php) | Inicialização, health check, alias `role` e `EnsureInternalAccount` no grupo web para revalidar contas autenticadas. |
| [app/Http/Controllers](../app/Http/Controllers) | Tratamento das ações HTTP; retorno de views ou redirecionamentos. |
| [app/Http/Requests](../app/Http/Requests) | Form Requests de login e perfil. Outros controllers validam diretamente. |
| [app/Http/Middleware](../app/Http/Middleware) | Autorização por papel. |
| [app/Models](../app/Models) | Conta autenticável e oito models administrativos com relações/casts; concessão e revogação auditadas em `Permissao`. |
| [AppServiceProvider](../app/Providers/AppServiceProvider.php) | Gates `atender-solicitacoes` e `gerenciar-permissoes`, sem liberação global do administrador para capacidades futuras. |
| [database](../database) | Migrations, factory e seeder; detalhes em [Modelagem](backend/MODELAGEM.md). |
| [resources/views](../resources/views) | Templates, layouts, componentes e formulários. |
| [app/View/Components](../app/View/Components) | Componentes de layout Blade. |
| [resources/css/app.css](../resources/css/app.css) e [resources/js/app.js](../resources/js/app.js) | Bootstrap, estilos e interações Alpine/modal. |
| [config](../config) | Configurações da aplicação; variáveis locais ficam fora do Git. |
| [public](../public) e [storage](../storage) | Entrada pública/assets e arquivos gerados em execução. |
| [tests](../tests) | Testes unitários e de funcionalidades web. |

## Backend e frontend

A base de agenda inclui models de agendamento, reserva e evento e rotas em `interno/agenda`. O Gate `gerenciar-agenda` é independente de `atender-solicitacoes`; ambos utilizam concessões auditadas existentes. As capacidades podem ser acumuladas, sem ampliar o enum de papéis e sem acesso clínico automático. As sete rotas de agenda exigem autenticação e o Gate antes de consultar os IDs.

As operações transacionais estão em `Agendamento` (confirmar, reagendar, cancelar e registrar não realização) e `ContatoSolicitacao::registrarAcompanhamento`. Validam permissão, estado, versão e contato; preservam histórico e bloqueiam sobreposição do responsável. `AgendaController` seleciona as entradas permitidas, delega a validação/gravação a esses métodos e traduz erros para formulários separados. As views `agenda.index/show/formulario` apresentam apenas identificação e contato necessários à visita, contatos de acompanhamento e histórico operacional; não incluem contatos/justificativas da análise. Detalhes em [Contratos](backend/CONTRATOS.md#fluxo-web-da-agenda-implementado-em-27102026).

O navegador recebe HTML, envia formulários com proteção CSRF e acompanha redirecionamentos. O backend autentica, valida, verifica permissões e persiste. O frontend exibe valores, erros e mensagens de sessão e oferece interações locais. Os detalhes do intercâmbio estão em [Contratos](backend/CONTRATOS.md).

Esconder links na navegação não substitui a autorização do servidor. As rotas usam autenticação/Gates e a navegação usa as mesmas capacidades. Login e recuperação de senha selecionam somente `admin` e `funcionario`. `EnsureInternalAccount` reconsulta a existência/papel da conta e encerra sessões sem conta interna válida. `FuncionarioController` cria somente funcionários com senha aleatória não divulgada e autoria; a primeira senha é definida pelo fluxo de recuperação por e-mail. O papel doadora não faz parte da arquitetura atual.

A página inicial demonstra Bootstrap; o painel apenas confirma a sessão; as páginas de papel demonstram acesso. Elas são apoio ao desenvolvimento, sem aprovação como interface final. Há telas funcionais de autenticação e perfil, mas sua existência também não comprova homologação visual.

Há base de domínio administrativo em models e autorização por Gates, além de recebimento, fila e detalhe de pedidos em `SolicitacaoController`, validação em `StoreSolicitacaoRequest` e views `solicitacoes`. Correspondências são consultadas no model `Solicitacao`, sem flags persistidas. Não foram adicionadas camadas próprias de serviços, repositórios ou API. O funcionamento e os limites estão em [Contratos](backend/CONTRATOS.md#recebimento-e-fila-implementados-em-24102026).

O atendimento administrativo usa `AtendimentoSolicitacaoController` e `AtendimentoSolicitacaoRequest`. Decisões transacionais ficam em `ResolucaoSolicitacao`, seguindo a convenção de operações administrativas nos models já usada para permissões. Contatos preservam autoria; resoluções preservam contato, motivo, versões e eventos. As views de acompanhamento apresentam somente a admissão e as etapas futuras pendentes. Ver [recorte implementado](backend/CONTRATOS.md#atendimento-administrativo-implementado-em-26102026).
