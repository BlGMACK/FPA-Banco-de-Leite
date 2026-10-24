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
| [routes/web.php](../routes/web.php) | Entrada pública, painel, perfil e rotas de teste por papel. |
| [routes/auth.php](../routes/auth.php) | Cadastro, sessão, senha e verificação de e-mail. |
| [routes/console.php](../routes/console.php) | Comando de exemplo `inspire`; não há agendamento de negócio nesse arquivo. |
| [bootstrap/app.php](../bootstrap/app.php) | Inicialização, health check e alias do middleware `role`. |
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

O navegador recebe HTML, envia formulários com proteção CSRF e acompanha redirecionamentos. O backend autentica, valida, verifica permissões e persiste. O frontend exibe valores, erros e mensagens de sessão e oferece interações locais. Os detalhes do intercâmbio estão em [Contratos](backend/CONTRATOS.md).

Esconder links na navegação não substitui a autorização do servidor. A aplicação usa middleware nas rotas de teste e condições Blade na navegação.

A página inicial demonstra Bootstrap; o painel apenas confirma a sessão; as páginas de papel demonstram acesso. Elas são apoio ao desenvolvimento, sem aprovação como interface final. Há telas funcionais de autenticação e perfil, mas sua existência também não comprova homologação visual.

Há base de domínio administrativo em models e autorização por Gates, ainda sem telas ou rotas de pedidos. Não foram adicionadas camadas próprias de serviços, repositórios ou API. O funcionamento e os limites da base estão em [Contratos](backend/CONTRATOS.md#base-de-models-e-autorização-implementada-em-24102026).
