# Bacurim API

API Laravel 12/PHP 8.3, organizada como monólito modular. A fundação inclui rotas `/api/v1`, contratos HTTP, testes, análise estática e CI. Funcionalidades financeiras, autenticação e tenancy efetiva serão implementadas nas próximas etapas.

## Começar pelo ambiente Docker

A infraestrutura local fica uma pasta acima deste repositório:

```text
Bacurim/
├── .env
├── docker-compose.yml
├── docker/api/
└── bacurim-api/
```

Requisitos do host: Git, Docker com Compose v2, terminal Linux/WSL2 e o Traefik existente na rede externa `dev-network`. PHP 8.3-FPM, Nginx, Composer 2 e extensões vêm da imagem da API. O Compose oferece PostgreSQL 18.6 e Redis 7. Clonar apenas a API não fornece os arquivos da infraestrutura externa; eles precisam estar disponíveis no workspace.

Siga o [guia Docker](docs/development-environment.md) para primeira instalação, DNS, credenciais e build. Não sobrescreva `.env` ou regenere `APP_KEY` de um ambiente existente. No primeiro setup, instale as dependências e gere a chave com os comandos documentados no guia antes de aguardar o health check.

Na raiz do workspace, para um ambiente já preparado:

```bash
docker compose up -d --build --wait
docker compose exec api php artisan config:clear
curl -i http://api-bacurim.mamura.test/api/v1/health
```

O endpoint responde HTTP 200 com `{"status":"ok"}`. Nginx atende na porta interna 80 e Traefik faz o roteamento pelo hostname. O health check confirma a resposta da aplicação, não a conectividade de banco/cache. `/up` continua disponível.

## Configuração

O `.env` da raiz fornece `POSTGRES_PASSWORD` ao Compose. O `.env` da API é independente e contém as variáveis Laravel. Ambos ficam fora do Git; `.env.example` da API documenta valores de bootstrap, sem credenciais reais.

| Variáveis | Uso no Docker |
| --- | --- |
| `APP_NAME`, `APP_ENV`, `APP_DEBUG` | Nome da API e ambiente. Debug é exclusivo do desenvolvimento. |
| `APP_KEY` | Chave exclusiva por ambiente, gerada na primeira instalação. |
| `APP_URL` | `http://api-bacurim.mamura.test`; não cria DNS/roteamento sozinho. |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT` | `pgsql`, `postgres`, `5432`. |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Banco/usuário configurados no Compose e mesma senha local fornecida na raiz. |
| `REDIS_CLIENT`, `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD` | `phpredis`, `redis`, `6379`, `null` para o serviço local sem autenticação. |
| `CACHE_STORE` | `redis` no ambiente Docker; o exemplo conserva `file` para bootstrap direto. |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `MAIL_MAILER` | Sessão/filas conforme configuração local; e-mail log no desenvolvimento. Não há worker dedicado no Compose. |

Timezone fixo em UTC em `config/app.php`. Não versionar senhas, tokens, chaves ou payloads de desenvolvimento. Os testes têm configuração própria e não recebem as credenciais locais. Mudanças no `.env` exigem `php artisan config:clear`; mudar a senha no Compose não altera um banco já inicializado automaticamente.

## Comandos da aplicação

Execute na raiz do workspace:

```bash
docker compose exec api php artisan route:list
docker compose exec api php artisan migrate:status
docker compose exec api php artisan migrate
docker compose exec api composer install
docker compose logs --tail=100 api postgres redis
```

Migrations alteram o banco configurado; execute intencionalmente e confira o ambiente. Não use migrate:fresh ou down -v para manutenção comum do desenvolvimento. Os scripts composer setup/dev ainda incluem tarefas do skeleton frontend e não são o fluxo principal da API.

## Testes e qualidade

```bash
docker compose --profile testing up -d --wait postgres-test
docker compose exec api composer check
```

`composer check` valida o Composer, verifica formatação, executa PHPStan/Larastan nível 6 e PHPUnit com as suítes Unit, Integration, Feature e Architecture. O PostgreSQL de testes é exclusivo e descartável; não usa o banco de desenvolvimento. `composer format` aplica correções; `composer format:check` só verifica. Comandos individuais e limites do verificador estão no [guia de testes](docs/testing-and-quality.md).

## Arquitetura e contratos

Código em `app/Modules/{IdentityAccess,Organizations,Financial,DataImport,Reporting,Forecasting}`, cada módulo com Domain, Application, Infrastructure e Interfaces. A estrutura inicial não implementa as entidades financeiras nem um mecanismo real de tenant.

Recursos usam Laravel API Resources com envelope data e paginação nativa. Erros usam RFC 9457, incluindo campos inválidos e códigos estáveis para falhas conhecidas. Datas instantâneas usam UTC e valores monetários são strings decimais com moeda explícita. Consulte [contratos HTTP](docs/http-api.md).

## CI e contribuição

[CI](docs/continuous-integration.md) executa em PRs para master e pushes na master, com PHP/PostgreSQL exclusivos da execução e sem segredos de produção. O job Quality reutiliza composer check. Branches, commits, revisão, Definition of Done e o template de PR seguem [CONTRIBUTING](CONTRIBUTING.md).

## Documentação técnica

O [índice de documentação](docs/README.md) reúne instalação, operação, arquitetura, ADRs, contratos, qualidade e CI. Para execução direta sem containers, consulte também os requisitos do guia Docker e adapte os hosts dos serviços; o fluxo validado do workspace é o Docker.
