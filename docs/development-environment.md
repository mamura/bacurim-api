# Ambiente de desenvolvimento Docker

Execute os comandos deste guia na raiz do workspace `Bacurim`, onde está o `docker-compose.yml`. A infraestrutura fica fora do repositório da API.

## Estrutura e requisitos

```text
Bacurim/
├── .env                     # senha local do PostgreSQL
├── docker-compose.yml
├── docker/api/
│   ├── Dockerfile
│   ├── nginx.conf
│   └── supervisord.conf
└── bacurim-api/
    ├── .env                 # configuração do Laravel
    └── composer.json
```

É necessário Docker Engine com Compose v2, Git e o Traefik existente conectado à rede externa `dev-network`. No Windows, execute pelo WSL2, com acesso ao Docker. PHP, Composer, PostgreSQL e Redis são fornecidos pelos containers. Os arquivos de infraestrutura devem estar disponíveis na raiz; clonar apenas `bacurim-api` não fornece o Compose e os Dockerfiles.

O hostname `api-bacurim.mamura.test` precisa resolver para o proxy local. A regra de host ou regra padrão do Traefik existente deve encaminhar esse hostname ao container `api-bacurim`. O Compose atual habilita a descoberta com `traefik.enable=true`; não declara uma regra de host explícita. O Nginx atende na porta interna 80, sem publicar essa porta no host.

```bash
docker version
docker compose version
docker network inspect dev-network
```

## Primeira instalação

Crie o `.env` da raiz com `POSTGRES_PASSWORD=<senha-local>`. Não reutilize literalmente o placeholder. Esse arquivo é lido pelo Compose e ignorado pelo Git da infraestrutura.

Somente se ainda não existir `bacurim-api/.env`:

```bash
cp bacurim-api/.env.example bacurim-api/.env
```

No `.env` do Laravel, ajuste:

```dotenv
APP_URL=http://api-bacurim.mamura.test
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=bacurim
DB_USERNAME=bacurim
DB_PASSWORD=<mesma-senha-do-env-da-raiz>
REDIS_CLIENT=phpredis
REDIS_HOST=redis
REDIS_PORT=6379
REDIS_PASSWORD=null
CACHE_STORE=redis
```

Os dois `.env` têm funções diferentes: o Compose não copia automaticamente sua senha para o Laravel. Preserve a `APP_KEY` e os demais valores de ambientes existentes. Mudar `POSTGRES_PASSWORD` não altera a senha de um usuário em um volume já inicializado; nesse caso, sincronize a alteração no banco e no Laravel.

Instale as dependências antes de iniciar o health check da aplicação:

```bash
docker compose config --quiet
docker compose build api
docker compose run --rm --no-deps api composer install
```

Na primeira instalação, gere a chave:

```bash
docker compose run --rm --no-deps api php artisan key:generate
```

Inicie todos os serviços e aguarde seus health checks:

```bash
docker compose up -d --wait
docker compose exec api php artisan config:clear
curl -i http://api-bacurim.mamura.test/api/v1/health
```

Resposta esperada: HTTP 200 e `{"status":"ok"}`. Esse endpoint verifica HTTP e bootstrap do Laravel, sem consultar PostgreSQL ou Redis.

## Serviços e persistência

- `api`: PHP 8.3-FPM e Nginx no mesmo container, gerenciados pelo Supervisor, executando como `www-data`. O código é montado em `/var/www/html`, e a raiz HTTP é `public/`.
- `postgres`: PostgreSQL 18.6, com volume lógico `bacurim_data` em `/var/lib/postgresql` e health check `pg_isready`.
- `redis`: Redis 7, com AOF habilitado, volume lógico `redis_data` em `/data` e health check `redis-cli ping`.

Os nomes físicos dos volumes recebem o prefixo do projeto Compose; mantenha o mesmo nome de projeto para reutilizá-los. A API aguarda PostgreSQL e Redis saudáveis na inicialização. `depends_on` não garante recuperação automática da aplicação se um serviço falhar depois.

A API participa da rede padrão, para acessar os serviços por nome, e da `dev-network`, para o Traefik. PostgreSQL e Redis participam apenas da rede padrão. As portas 5432 e 6379 permanecem publicadas por decisão do projeto para acesso no desenvolvimento local; o bind atual usa todas as interfaces do host.

As filas mantêm o driver definido em `QUEUE_CONNECTION` no `.env` local. Não há worker dedicado no Compose atual. A disponibilidade do Redis e seu uso no cache não iniciam o processamento de filas.

## Comandos usuais

```bash
# Estado e logs
docker compose ps
docker compose logs --tail=100 api postgres redis
docker compose logs -f api

# Atualizar o ambiente após mudar a infraestrutura
docker compose up -d --build --wait

# Parar e iniciar, mantendo dados
docker compose stop
docker compose up -d --wait

# Remover containers e redes do projeto, mantendo volumes nomeados
docker compose down
docker compose up -d --wait

# Artisan, Composer e qualidade
docker compose exec api php artisan route:list
docker compose exec api php artisan config:clear
docker compose exec api composer install
docker compose exec api composer validate --strict
docker compose exec api composer check-platform-reqs
docker compose exec api composer test
docker compose exec api vendor/bin/pint --test
```

Não use `docker compose down -v` para uma parada normal: esse comando remove os volumes e seus dados. Migrations alteram o banco e devem ser executadas intencionalmente com `docker compose exec api php artisan migrate`; não são executadas automaticamente ao iniciar o ambiente.

## Verificar conexões

```bash
docker compose exec api php artisan tinker
```

Dentro do Tinker:

```php
DB::selectOne('select current_database() as database, version() as version');
app('redis')->connection()->ping();
app('cache')->store('redis')->put('development_connection_check', 'ok', 30);
app('cache')->store('redis')->pull('development_connection_check');
```

A última operação lê e remove a chave temporária. Use `app('redis')` para evitar conflito entre o nome da extensão PHP Redis e a facade do Laravel.

## Troubleshooting

| Sintoma | Ação |
| --- | --- |
| Compose exige `POSTGRES_PASSWORD` | Preencha o `.env` da raiz. Use `docker compose config --quiet` para validar sem imprimir a configuração com a senha. |
| Laravel não conecta ao banco | Confira `pgsql`, `postgres:5432`, banco, usuário e senha no `.env` da API; execute `config:clear`. |
| Redis não conecta | Confira `REDIS_HOST=redis`, cliente `phpredis`, `REDIS_PASSWORD=null` para o serviço sem autenticação e os logs do serviço. |
| Hostname não resolve | Confira o DNS/hosts local. `curl --resolve api-bacurim.mamura.test:80:127.0.0.1 http://api-bacurim.mamura.test/api/v1/health` testa o roteamento local sem depender do DNS. |
| Traefik retorna 404/502 | Confira sua descoberta Docker, regra de host, rede `dev-network`, porta interna 80 e os logs. Não inicie outro proxy nem publique a porta 80 da API. |
| API unhealthy | Confira `docker compose logs api`, instalação de `vendor/`, `.env`, chave e permissões. |
| Erro de escrita no bind mount | Confira `id -u` e `id -g` no WSL. O Dockerfile aceita `APP_UID` e `APP_GID` como argumentos de build; os padrões são 1000. |
| Porta 5432 ou 6379 ocupada | Identifique o serviço em conflito ou altere somente a porta do host. A API continua usando as portas internas pelos nomes dos serviços. |

## Validação da issue #11

Validação concluída: os três serviços ficaram saudáveis; PostgreSQL e Redis responderam pelo Laravel; o health check retornou HTTP 200 pelo Traefik; dados temporários sobreviveram à recriação dos containers com os volumes preservados e foram removidos ao final. Os cinco testes da aplicação passaram. Os testes automatizados atuais continuam sem dependência de banco; a estratégia completa pertence à issue #25.

Voltar ao [README](../README.md).
