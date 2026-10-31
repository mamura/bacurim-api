# Estratégia de testes e qualidade

## Ambiente PHPUnit (#26)

PHPUnit 11 está fixado pelo composer.lock e é executado com `composer test`, dentro do container `api`. `phpunit.xml` é a fonte das variáveis de teste; não é necessário copiar um `.env.testing`. Os valores críticos usam `force=true` para não herdar APP_ENV, drivers, URL de banco, chave ou debug do processo de desenvolvimento.

O bootstrap `tests/bootstrap.php` exige APP_ENV=testing e impede carregar um cache específico de testes. Os caminhos de cache de configuração e rotas são separados dos caches de desenvolvimento. Sessão e cache usam array, e-mail array, filas sync e logs null. Testes que verificam logging usam spies. Não iniciar workers, enviar e-mails ou chamar serviços reais por efeito colateral de testes.

`composer test` chama PHPUnit diretamente e não executa `artisan config:clear` sobre o ambiente de desenvolvimento. A chave fixa é exclusiva dos testes, sem segredo real. A configuração de persistência exclusiva está definida na seção da #28 abaixo; unitários e contratos sem persistência continuam sem consultar banco.

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

## Verificações arquiteturais (#30)

A suíte Architecture usa nikic/php-parser, declarado diretamente como dependência de desenvolvimento, para analisar a AST dos arquivos PHP em app/. Ela resolve nomes, aliases e imports agrupados, verifica tipos e referências estáticas e constrói o grafo de dependências entre módulos.

Regras implementadas:

- Domain só referencia a própria camada de seu módulo e o Shared Kernel permitido; Application não importa Interfaces/Infrastructure.
- Interfaces não acessa Infrastructure; Infrastructure não usa Interfaces fora de seu provider de composição.
- Domain/Application/Shared Kernel não importam Illuminate, Symfony, PDO ou Redis, nem usam os helpers de framework listados no verificador.
- Módulos cruzam fronteiras somente por Application/Contracts/PublicApi, nas direções do mapa. Domain não integra diretamente outro módulo.
- Shared Kernel não depende de módulos ou infraestrutura global de App; Domain não depende de SharedKernel Application.
- Código de produção não depende de Tests; módulos desconhecidos ou namespaces de módulo fora das quatro camadas são rejeitados.
- O grafo de referências entre módulos não pode conter ciclos.

O mapa permitido está explícito em Tests\Support\ArchitectureInspector e deve mudar junto com a documentação ao adicionar um módulo. O teste sobre app/ protege o código real; exemplos sintéticos válidos e inválidos verificam que as regras não passam apenas porque os módulos ainda estão vazios. Há cenários de alias, import agrupado, FQCN, helper de framework, contrato público permitido, provider e ciclo.

```bash
docker compose exec api composer test -- --testsuite=Architecture
```

Limitações: análise estática de nomes PHP, não prova semântica de regras de negócio nem isolamento em runtime. Não resolve nomes de classes construídos dinamicamente, strings de container/reflection, SQL, referências em PHPDoc ou comunicação por eventos configurada indiretamente. Não garante que uma classe com namespace permitido esteja no arquivo correto; Composer strict-psr verifica esse aspecto para classes autoloaded. Helpers proibidos são uma lista explícita, não uma detecção universal de efeitos externos. Revisão de código e testes de comportamento continuam necessários. Erros de parsing também fazem a suíte falhar.

## Fluxo antes de commits e pull requests (#31)

Na raiz do workspace, prepare as dependências e o serviço exclusivo de testes:

```bash
docker compose --profile testing up -d --wait postgres-test
docker compose exec api composer install
docker compose exec api composer check
```

`composer check` executa, em ordem, validação estrita do Composer, Pint sem modificar fontes, PHPStan/Larastan e a suíte completa PHPUnit (incluindo arquitetura e integração PostgreSQL). Retorna no primeiro comando com falha e propaga código diferente de zero, para uso futuro no CI. Não configura GitHub Actions nesta etapa.

Comandos individuais:

```bash
docker compose exec api composer test:unit
docker compose exec api composer test:integration
docker compose exec api composer test:api
docker compose exec api composer test:architecture
docker compose exec api composer analyse
docker compose exec api composer format:check
```

Corrija a causa de falhas, rode o teste/check afetado e depois `composer check` antes do commit. Se usar `composer format`, revise o diff antes de repetir os checks. Não atualizar baseline, desabilitar guardas de banco ou ignorar regras arquiteturais para acomodar uma regressão.

Para CI sem o Compose do workspace: instalar PHP 8.3+ com extensões do composer.lock e pdo_pgsql, executar composer install com require-dev, disponibilizar PostgreSQL 18.6 com as credenciais exclusivas documentadas na #28, definir TEST_DB_HOST/PORT quando necessário e executar composer check. Git, unzip e Composer 2 também são necessários para instalação. Não usar composer install --no-dev no job de qualidade.

A suíte não precisa de Redis, Traefik ou Nginx para seus contratos HTTP: o kernel de teste recebe as requisições em processo. A integração precisa somente do PostgreSQL exclusivo. Não passar credenciais do ambiente real como forma de preparar os testes. Não executar a suíte em paralelo até configurar isolamento por processo.

## Evidências da validação

- Unitários usam PHPUnit puro e verificam o contrato de falhas da aplicação.
- Integração PostgreSQL executada repetidamente com migrations, factories e rollback, sem usar o serviço de desenvolvimento.
- Testes HTTP existentes preservados e aprovados.
- Análise estática nível 6 sem baseline ou erros; Pint aprovado.
- Regras arquiteturais exercitadas com código real e exemplos positivos/negativos.
- PHPStan e Pint rejeitaram arquivos temporários com violações conhecidas, com exit code 1; os arquivos foram removidos.

Resultados finais e números de testes devem ser consultados na execução atual, não tratados como uma meta fixa de cobertura. O ambiente e os comandos estão prontos para a configuração do pipeline na próxima etapa.

O bootstrap sincroniza as variáveis forçadas de phpunit.xml em getenv, $_ENV e $_SERVER, pois Laravel pode priorizar $_SERVER. A validação final também executa contratos HTTP com APP_ENV=production, DB_DATABASE=bacurim e CACHE_STORE=redis herdados do processo; as configurações de teste devem prevalecer. A guarda de banco permanece ativa como segunda defesa antes das migrations.
