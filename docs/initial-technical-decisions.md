# Decisões tecnológicas iniciais

Registro da fundação da API, consolidado na issue [#5](https://github.com/mamura/bacurim-api/issues/5), parte da issue [#1](https://github.com/mamura/bacurim-api/issues/1), no milestone API-M0 — Foundation.

## Runtime e dependências

A aplicação usa Laravel 12 e PHP `^8.3`. O framework fornece roteamento, configuração, injeção de dependências e ferramentas de execução. A estrutura padrão foi preservada para receber a arquitetura modular prevista na issue #12.

O Composer usa `config.platform.php=8.3.0` para resolver pacotes compatíveis com a versão mínima, mesmo em máquinas com PHP mais recente. Isso não substitui a validação no runtime real. O `composer.lock` é versionado e a instalação usa `composer install`; `composer update` fica reservado a alterações intencionais nas dependências.

## Configuração e datas

O `.env.example` documenta parâmetros públicos e placeholders. Cada ambiente mantém sua própria `APP_KEY` e credenciais fora do Git. `.env` e suas variantes são ignorados, com exceção do arquivo de exemplo. `APP_DEBUG=true` é destinado apenas ao desenvolvimento.

O timezone permanece fixo em UTC no `config/app.php`, para que a aplicação use uma referência temporal consistente entre máquinas. Conversões para exibição e regras de timezone de usuários ficam para as funcionalidades futuras.

## Persistência e serviços

PostgreSQL 18.6 foi adotado por decisão do projeto durante a configuração Docker, substituindo a versão 16 originalmente prevista. O exemplo declara a conexão `pgsql`; os serviços e a conectividade estão configurados no ambiente Docker. As migrations, factories e o modelo de usuário atuais são padrões do Laravel, sem modelagem financeira implementada.

Redis 7 está disponível no ambiente Docker com cliente PHP `phpredis`, AOF e volume persistente. O cache local usa Redis; o exemplo mantém sessão/cache em arquivos e fila síncrona para bootstrap direto. Não há worker de filas dedicado no Compose. Os prefixos de cache e Redis identificam a API para reduzir colisões com outras aplicações; não representam isolamento entre tenants.

O e-mail usa o driver `log` no desenvolvimento. Nenhum serviço SMTP ou AWS é necessário para executar o health check.

## Entrada HTTP

As rotas públicas da API são registradas em `routes/api.php`, sob `/api/v1`, usando o grupo de middleware `api` do Laravel. Autenticação, autorização e multi-tenancy serão implementados nas etapas específicas.

`GET /api/v1/health` retorna HTTP 200 e `{"status":"ok"}`. Ele verifica a capacidade da aplicação de responder HTTP, sem testar PostgreSQL ou Redis. O `/up` padrão do Laravel foi mantido.

Os erros em `/api` e `/api/*` são renderizados em JSON independentemente do cabeçalho `Accept`. O tratamento central usa RFC 9457 (Problem Details) conforme as [convenções HTTP](http-api.md), estabelecidas na issue #19.

O servidor Artisan permite validar a aplicação localmente nesta etapa. O ambiente Docker reutiliza o Traefik existente e a rede `dev-network`, com o hostname `api-bacurim.mamura.test`. A integração foi validada na issue #10; `APP_URL` sozinho não cria esse roteamento.

## Validação inicial

PHPUnit executa os testes iniciais, incluindo o contrato do health check e os erros HTTP da API. Sessão/cache de testes usam memória; testes de integração usam PostgreSQL 18.6 exclusivo com migrations e rollback. A [estratégia de testes](testing-and-quality.md) documenta o isolamento, a análise estática e as regras arquiteturais estabelecidas na #25.

Laravel Pint verifica a formatação, e PHPStan/Larastan analisa o código no nível 6. CI e documentação técnica consolidada estão previstos na issue #32. Os arquivos Vite e da página web padrão permanecem no skeleton, mas Node.js não é necessário para executar a API e os testes atuais.

## Limites de escopo

Este setup não define a arquitetura definitiva dos módulos, regras financeiras, autenticação, multi-tenancy ou deploy de produção. As próximas issues complementam a base antes das funcionalidades de negócio.

Voltar ao [README](../README.md).
