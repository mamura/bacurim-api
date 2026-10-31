# Estratégia de testes e qualidade

## Ambiente PHPUnit (#26)

PHPUnit 11 está fixado pelo composer.lock e é executado com `composer test`, dentro do container `api`. `phpunit.xml` é a fonte das variáveis de teste; não é necessário copiar um `.env.testing`. Os valores críticos usam `force=true` para não herdar APP_ENV, drivers, URL de banco, chave ou debug do processo de desenvolvimento.

O bootstrap `tests/bootstrap.php` exige APP_ENV=testing e impede carregar um cache específico de testes. Os caminhos de cache de configuração e rotas são separados dos caches de desenvolvimento. Sessão e cache usam array, e-mail array, filas sync e logs null. Testes que verificam logging usam spies. Não iniciar workers, enviar e-mails ou chamar serviços reais por efeito colateral de testes.

`composer test` chama PHPUnit diretamente e não executa `artisan config:clear` sobre o ambiente de desenvolvimento. A chave fixa é exclusiva dos testes, sem segredo real. Dados de conexão de persistência serão estabelecidos na #28; testes atuais não usam banco.

Na raiz do workspace:

```bash
docker compose exec api composer test
docker compose exec api composer test -- --filter=ApiHealthTest
docker compose exec api composer test -- --testsuite=Unit
```

Os argumentos são encaminhados ao PHPUnit; falhas retornam código diferente de zero. Para um arquivo específico, use `docker compose exec api composer test -- tests/Feature/ApiHealthTest.php`. Não usar cache de configuração da aplicação em testes.

## Organização e política de testes (#27)

| Suíte | Responsabilidade | Base |
| --- | --- | --- |
| `tests/Unit` | Comportamento puro de Domain/Application, sem inicializar Laravel, banco ou serviços. | PHPUnit\Framework\TestCase |
| `tests/Integration` | Mecanismos reais de persistência e composição que precisam do framework ou PostgreSQL de testes. | Tests\TestCase e base de persistência dedicada quando aplicável |
| `tests/Feature` | API e comportamento observável via HTTP; fixtures de contrato sem endpoints de negócio artificiais. | Tests\TestCase |
| `tests/Architecture` | Dependências de código, limites de camadas/módulos e ciclos. | PHPUnit\Framework\TestCase quando não precisar do framework |
| `tests/Fixtures` | Adaptadores e tipos auxiliares usados pelos exemplos de contrato. Não é uma suíte. | Sem dependência de código de produção com Tests\ |

Arquivos terminam em Test.php; métodos descrevem o comportamento esperado. Organizar subdiretórios por módulo conforme surgirem implementações reais. Não duplicar fixtures para cada caso nem testar somente chamadas internas sem efeito observável.

Unitários de domínio cobrem invariantes, limites, transições e exceções. Casos de uso usam fakes pequenos de suas portas, verificando resultados e efeitos relevantes. Mocks são adequados para interações externas cujo protocolo precise ser observado, como logging, envio ou falha controlada; evitar cadeias de mocks de ORM e testes que reproduzem cada linha do caso de uso.

Integrações de persistência usam PostgreSQL real exclusivo para testes; SQLite não substitui a validação de SQL, constraints e transações do PostgreSQL. Fixtures/factories devem ser explícitas e pequenas, sem depender da ordem de execução ou de dados pré-existentes. API testa serialização, headers, códigos, autorização quando implementada e ausência de dados sensíveis. Não chamar APIs externas reais em testes recorrentes.

Toda alteração em regra financeira deve incluir casos positivos, negativos e fronteiras relevantes; correção de bug exige um teste que reproduza a falha. Invariantes sobre realização, saldo, arredondamento, histórico e tenant devem ser testadas na camada que as garante. Não criar regras financeiras só para gerar cobertura, nem exigir 100% como substituto de qualidade.

Testes são independentes: não compartilham usuários, tenants, relógio global, cache ou estado residual entre execuções. Datas sensíveis a tempo devem usar relógio controlado. Uma regressão deve falhar antes da correção e passar depois, sem enfraquecer o contrato.

Execute uma suíte com `composer test -- --testsuite=Unit` (ou Integration, Feature, Architecture). Banco de testes é necessário somente para a suíte que usa persistência, não para unitários ou contratos HTTP sem banco.

## PostgreSQL exclusivo e isolamento (#28)

O Compose da raiz fornece `postgres-test` no profile `testing`, PostgreSQL 18.6, sem porta publicada. Usa banco/usuário `bacurim_test` e senha pública exclusivamente de teste `testing-only`, sem permissões no serviço de desenvolvimento. O armazenamento tmpfs é descartável; o serviço de desenvolvimento e seus volumes não são utilizados.

```bash
docker compose --profile testing up -d --wait postgres-test
docker compose exec api composer test -- --testsuite=Integration
docker compose exec api composer test
```

A configuração PHPUnit força pgsql, banco/usuário de teste e DB_URL vazio. Tests\TestCase verifica essas condições ao criar a aplicação, antes de RefreshDatabase executar migrations. Cache/rotas de teste ficam separados. Não altere essa proteção para apontar a suíte ao banco `bacurim`.

`RefreshDatabase` prepara o schema pelas migrations existentes na primeira utilização e usa transações para desfazer dados de cada teste. O processo precisa de privilégios para criar/limpar tabelas somente no banco descartável. Não execute `migrate:fresh` manualmente no serviço de desenvolvimento. Factories criam somente os registros necessários, sem seed automático compartilhado. Os testes atuais usam o modelo User do skeleton, sem implementar usuários/tenancy de negócio.

`PostgreSqlPersistenceTest` verifica migrations, criação por factory, rollback entre casos e constraint única real do PostgreSQL. A suíte pode ser repetida sem depender dos dados anteriores. O modo paralelo ainda não está configurado: cada processo concorrente exigirá banco/serviço próprio e revisão da proteção, não compartilhar esse banco entre workers concorrentes.

Em CI, crie um serviço PostgreSQL com o mesmo banco, usuário e senha de teste. Se o serviço estiver acessível por outro endereço, defina TEST_DB_HOST e TEST_DB_PORT; apenas o endereço muda, não o banco/role protegidos. Para encerrar o serviço local: `docker compose --profile testing stop postgres-test`. A configuração da infraestrutura fica fora do Git da API, conforme a organização do workspace.

Configuração equivalente para reproduzir o serviço onde o Compose do workspace não estiver disponível:

```yaml
services:
  postgres-test:
    image: postgres:18.6
    environment:
      POSTGRES_DB: bacurim_test
      POSTGRES_USER: bacurim_test
      POSTGRES_PASSWORD: testing-only
    tmpfs:
      - /var/lib/postgresql
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U bacurim_test -d bacurim_test"]
      interval: 2s
      timeout: 3s
      retries: 15
```

A aplicação de testes precisa estar na mesma rede ou receber TEST_DB_HOST/PORT de acesso ao serviço. Essa configuração é exclusiva para testes; não contém credenciais de desenvolvimento.

## Análise estática e estilo (#29)

PHPStan 2 com Larastan 3 e a extensão Carbon analisa `app/`, bootstrap e rotas no nível inicial 6, sem baseline nem ignores genéricos. Configuração em `phpstan.neon`; cache em storage/framework/testing/phpstan, ignorado pelo Git. Os testes continuam verificados em execução pelo PHPUnit e pelo Pint; a configuração inicial do PHPStan cobre o código da aplicação, não o código de testes.

Pint usa o preset Laravel explicitamente em `pint.json`. Código deve seguir PSR-4 e tipos explícitos, com PHPDoc de generics/arrays quando necessário para análise; não silenciar erros para passar um check sem justificar a limitação.

```bash
docker compose exec api composer analyse
docker compose exec api composer format:check
docker compose exec api composer format
```

Analyse e format:check são verificações sem modificar o código-fonte e retornam erro quando há violação. Format aplica correções de estilo e deve ser seguido de revisão do diff. Larastan inicializa o container Laravel para inferir tipos: não adicionar consultas ao banco ou efeitos externos em providers, e não incluir credenciais em configurações de análise.
