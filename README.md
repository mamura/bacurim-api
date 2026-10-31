# Bacurim API

Backend do Bacurim com Laravel 12 e PHP 8.3 ou superior.

## Estado atual

Bootstrap e configuração inicial do ambiente referentes às issues [#2](https://github.com/mamura/bacurim-api/issues/2) e [#3](https://github.com/mamura/bacurim-api/issues/3), no milestone API-M0 — Foundation. A estrutura padrão do Laravel foi preservada para receber as próximas etapas.

As rotas da API e o health check estão configurados conforme a issue [#4](https://github.com/mamura/bacurim-api/issues/4). Docker e arquitetura modular serão tratados nas próximas issues. O endereço `api-bacurim.mamura.test` será integrado ao Traefik existente na issue #10.

## Requisitos

- PHP 8.3 ou superior, com as extensões exigidas pelo Composer.
- Composer 2 e Git.
- Extensão `pdo_pgsql` e PostgreSQL 16 para operações com banco. O serviço será configurado na issue #9.

O Composer resolve as dependências usando PHP 8.3.0 como referência. Versione o `composer.lock` para instalações reproduzíveis.

## Instalação

Execute no terminal Linux/WSL, dentro de `bacurim-api`:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

O `.env` é local e ignorado pelo Git. Não publique a chave da aplicação.

Para executar a aplicação antes de configurar os serviços externos:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

O Laravel responde em `http://127.0.0.1:8000`. O endpoint padrão `GET /up` verifica o bootstrap sem acessar o banco e pode ser utilizado antes de executar as migrations.

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
- `SESSION_DRIVER=file`, `CACHE_STORE=file` e `QUEUE_CONNECTION=sync`: permitem executar a aplicação sem banco ou Redis. A integração Redis será feita no ambiente Docker.
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

## Estrutura inicial

- `app/`: código da aplicação e providers.
- `bootstrap/`: inicialização do framework.
- `config/`: configurações.
- `database/`: migrations, factories e seeders padrão.
- `public/`: entrada HTTP.
- `routes/`: rotas.
- `tests/`: testes iniciais de unidade e HTTP.

Os arquivos Vite e os recursos da página padrão foram preservados do skeleton oficial. Node.js não é necessário para executar `/up` ou os testes iniciais.
