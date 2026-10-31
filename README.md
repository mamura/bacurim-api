# Bacurim API

Backend do Bacurim com Laravel 12 e PHP 8.3 ou superior.

## Estado atual

Bootstrap e configuração inicial do ambiente referentes às issues [#2](https://github.com/mamura/bacurim-api/issues/2) e [#3](https://github.com/mamura/bacurim-api/issues/3), no milestone API-M0 — Foundation. A estrutura padrão do Laravel foi preservada para receber as próximas etapas.

As rotas da API e o health check estão configurados conforme a issue [#4](https://github.com/mamura/bacurim-api/issues/4). O ambiente Docker usa PostgreSQL 18.6, Redis 7 e Nginx/PHP-FPM no mesmo container, com acesso pelo Traefik em `api-bacurim.mamura.test`. A arquitetura modular será definida nas próximas issues.

## Ambiente Docker (fluxo principal)

Consulte [Ambiente de desenvolvimento Docker](docs/development-environment.md) para instalação, comandos, conexões, persistência e troubleshooting. O Compose e a pasta `docker/` ficam na raiz do workspace, uma pasta acima de `bacurim-api`.

As instruções de instalação direta pelo Artisan abaixo são uma alternativa sem containers.

## Requisitos

- PHP 8.3 ou superior, com as extensões exigidas pelo Composer.
- Composer 2 e Git.
- Terminal Linux ou WSL2 no Windows. Os comandos abaixo usam sintaxe Bash.
- `curl` para conferir a resposta HTTP pelo terminal.
- Extensão `pdo_pgsql` e PostgreSQL 18.6 para operações com banco em execução direta. No Docker, runtime e extensões já estão incluídos na imagem.

Confira as ferramentas antes de começar:

```bash
php --version
composer --version
git --version
```

O Composer verifica as extensões exigidas pelos pacotes, incluindo `dom`, `fileinfo`, `mbstring`, `openssl`, `PDO`, `session`, `tokenizer`, `xml` e `xmlwriter`. Algumas podem ser fornecidas por polyfills; `composer check-platform-reqs` verifica os requisitos no PHP que executa o comando. `curl` e `zip` como extensões PHP são úteis para agilizar os downloads; sem `zip`, disponibilize `unzip` ou `7z` para extrair os pacotes.

PostgreSQL e Redis não são necessários para o health check nem para os testes atuais. A extensão `redis` será necessária ao usar o cliente `phpredis`. Node.js e npm são necessários apenas para os scripts do frontend padrão preservados no skeleton.

O Composer resolve as dependências usando PHP 8.3.0 como referência. Versione o `composer.lock` para instalações reproduzíveis.

## Instalação

Para uma primeira instalação, clone o repositório e entre na pasta:

```bash
git clone https://github.com/mamura/bacurim-api.git
cd bacurim-api
```

Se já tiver o repositório local, use a pasta existente. Instale as dependências de desenvolvimento e prepare o ambiente:

```bash
composer install
cp .env.example .env
php artisan key:generate
composer check-platform-reqs
```

O `.env` é local e ignorado pelo Git. Não publique a chave da aplicação.

O comando `cp` acima é exclusivo da primeira instalação, quando ainda não existe um `.env` local. Em instalações existentes, preserve o arquivo e sua `APP_KEY`. Ajuste `APP_URL=http://127.0.0.1:8000` no `.env` para execução direta pelo Artisan.

Garanta que o usuário que executa o PHP tenha permissão de escrita em `storage/` e `bootstrap/cache/`. No WSL, prefira manter o projeto no sistema de arquivos Linux.

Para executar a aplicação antes de configurar os serviços externos:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

O Laravel responde em `http://127.0.0.1:8000`. O endpoint padrão `GET /up` verifica o bootstrap sem acessar o banco e pode ser utilizado antes de executar as migrations.

Mantenha esse terminal aberto e execute o `curl` da seção seguinte em outro terminal. Para parar o servidor, pressione `Ctrl+C`.

Os scripts `composer setup` e `composer dev` ainda são os padrões do Laravel: o primeiro executa migrations e instala/builda o frontend; o segundo inicia também fila, logs e Vite. Para o setup inicial da API, utilize os comandos individuais documentados aqui.

## Entrada da API

As rotas de `routes/api.php` usam o grupo de middleware `api` do Laravel e o prefixo `/api/v1`, sem sessão ou autenticação nesta etapa.

```bash
curl -i http://127.0.0.1:8000/api/v1/health
```

O endpoint `GET /api/v1/health` responde HTTP 200, com `Content-Type: application/json` e corpo:

```json
{"status":"ok"}
```

Esse health check verifica se a aplicação consegue responder HTTP; não consulta PostgreSQL ou Redis. Ele funciona antes de configurar os serviços externos. O `/up` padrão do Laravel permanece disponível.

As respostas de erro nos caminhos `/api` e `/api/*` também são JSON, mesmo sem `Accept: application/json` ou quando o cliente solicita HTML. Nesta etapa, os erros usam o formato padrão do Laravel; as convenções definitivas serão implementadas na issue #19. Rotas web preservam a negociação de conteúdo padrão.

## Ambiente

O `.env.example` contém apenas parâmetros públicos e valores de exemplo. Ao atualizar um ambiente existente, copie apenas os parâmetros necessários para o `.env`, preservando sua `APP_KEY` e credenciais.

- `APP_NAME`: identifica a aplicação como Bacurim API.
- `APP_ENV=local` e `APP_DEBUG=true`: valores exclusivos de desenvolvimento. Em produção, use `APP_ENV=production` e `APP_DEBUG=false`.
- `APP_URL`: endereço público previsto no Traefik. Esse parâmetro não configura o proxy nem o DNS. Para execução direta pelo Artisan, use `http://127.0.0.1:8000` no `.env`.
- Timezone: fixo em `UTC` no `config/app.php`, independentemente do timezone da máquina. Não há variável `APP_TIMEZONE`.
- `DB_*`: conexão PostgreSQL, porta 5432 e banco/usuário de exemplo `bacurim`. Preencha a senha localmente antes de acessar o banco; em Docker, o hostname será o nome do serviço definido no Compose.
- `SESSION_DRIVER=file`, `CACHE_STORE=file` e `QUEUE_CONNECTION=sync`: permitem executar a aplicação sem banco ou Redis. No ambiente Docker, use `CACHE_STORE=redis` e `REDIS_HOST=redis`.
- `REDIS_*`: cliente `phpredis`, porta 6379, bancos 0 e 1 e prefixo exclusivo da API. A extensão PHP Redis será necessária quando o serviço for utilizado.
- `MAIL_MAILER=log`: mensagens são registradas localmente, sem envio por SMTP.

Mantenha `APP_KEY`, senhas, tokens e chaves AWS apenas no `.env` ou no mecanismo de segredos do ambiente. O Git ignora `.env` e suas variantes, permitindo apenas `.env.example`. Os valores de logs, e-mail e AWS do exemplo são destinados ao desenvolvimento e devem ser revisados ao configurar cada ambiente.

Depois de alterar variáveis, execute `php artisan config:clear`. Para validar a configuração compilada, use `php artisan config:cache` e depois `php artisan config:clear`; o cache gerado também é ignorado pelo Git.

## Validação

```bash
composer validate --strict
composer check-platform-reqs
php artisan --version
php artisan route:list
composer test
vendor/bin/pint --test
```

Os testes iniciais usam sessão e cache em memória e não precisam de um banco em execução. A estratégia de testes será ampliada na issue #25.

## Problemas comuns

| Sintoma | Verificação ou ação |
| --- | --- |
| Composer acusa versão ou extensão incompatível | Confira o PHP do terminal com `php --version`, `php --ini` e `composer check-platform-reqs`. Instale a extensão no mesmo PHP usado pelo Composer. |
| Erro de chave de criptografia | Na primeira instalação, copie o exemplo e execute `php artisan key:generate`. Preserve chaves de ambientes existentes. |
| Alterações no `.env` não aparecem | Execute `php artisan config:clear`. |
| Erro de escrita em cache ou logs | Confira proprietário e permissões de `storage/` e `bootstrap/cache/`. |
| Porta 8000 ocupada | Use `php artisan serve --host=127.0.0.1 --port=8001` e ajuste a URL do `curl` e `APP_URL`. |
| Hostname `api-bacurim.mamura.test` não responde | Confira o DNS local, a rede `dev-network` e o roteamento do Traefik conforme o guia Docker. |
| Erro de conexão ao banco | O health check não exige banco. Para operações com banco, configure PostgreSQL e `DB_*`; em ambientes antigos, confira também `SESSION_DRIVER=file` e `CACHE_STORE=file`. |

## Decisões tecnológicas

As escolhas iniciais e seus limites estão registradas em [Decisões tecnológicas iniciais](docs/initial-technical-decisions.md). Esse registro cobre a fundação executável; a arquitetura modular e suas regras serão definidas na issue #12.

## Arquitetura em definição

O [mapa dos contextos](docs/context-map.md) define responsabilidades, conceitos compartilhados e integrações permitidas com base nos documentos de produto. A issue #12 segue em andamento; o mapa corresponde à subtarefa #13 e será complementado por camadas, estrutura física, providers e ADRs.

## Estrutura inicial

- `app/`: código da aplicação e providers.
- `bootstrap/`: inicialização do framework.
- `config/`: configurações.
- `database/`: migrations, factories e seeders padrão.
- `public/`: entrada HTTP.
- `routes/`: rotas.
- `tests/`: testes iniciais de unidade e HTTP.

Os arquivos Vite e os recursos da página padrão foram preservados do skeleton oficial. Node.js não é necessário para executar `/up` ou os testes iniciais.
