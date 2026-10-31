# Bacurim API

Backend do Bacurim com Laravel 12 e PHP 8.3 ou superior.

## Estado atual

Bootstrap referente à [issue #2](https://github.com/mamura/bacurim-api/issues/2), no milestone API-M0 — Foundation. A estrutura padrão do Laravel foi preservada para receber as próximas etapas.

Configuração do ambiente, rotas `/api/v1`, health check da API, Docker e arquitetura modular serão tratados nas respectivas issues. O endereço `api-bacurim.mamura.test` será integrado ao Traefik existente na issue #10.

## Requisitos

- PHP 8.3 ou superior, com as extensões exigidas pelo Composer.
- Composer 2 e Git.
- SQLite e extensão `pdo_sqlite` para executar as migrations padrão nesta etapa.

O Composer resolve as dependências usando PHP 8.3.0 como referência. Versione o `composer.lock` para instalações reproduzíveis.

## Instalação

Execute no terminal Linux/WSL, dentro de `bacurim-api`:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

O `.env` é local e ignorado pelo Git. Não publique a chave da aplicação.

Para executar a página inicial com sessão e cache em banco, prepare o SQLite:

```bash
touch database/database.sqlite
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

O Laravel responde em `http://127.0.0.1:8000`. O endpoint padrão `GET /up` verifica o bootstrap sem acessar o banco e pode ser utilizado antes de executar as migrations.

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
