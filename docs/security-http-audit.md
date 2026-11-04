# Auditoria HTTP e exposição de dados (#78)

Escopo: endpoints da API-M1, sessão SPA, erros, logs Monolog e dependências instaladas. Revisão realizada em 2026-11-04, com dados exclusivamente sintéticos.

## Achados e correções

| Achado | Correção | Evidência |
| --- | --- | --- |
| Sete avisos em quatro pacotes Symfony 7.4.22 | http-foundation, http-kernel, routing e yaml atualizados para 7.4.23 | composer audit --locked sem avisos após atualização |
| Senhas interpoladas e metadados do contexto do framework chegavam aos handlers | SafeLogManager aplica RedactSensitiveLog após os demais processadores, inclusive no logger de emergência | Regressão reproduzida antes da correção; HttpSecurityAuditTest e LogRedactionTest |
| Cabeçalhos de proteção ausentes | nosniff, no-referrer e DENY nas respostas API/CSRF; HSTS somente quando a requisição é HTTPS reconhecida | Sucesso, erro, HTTPS e forwarded proto de origem não confiável |
| Diagnóstico do PostgreSQL no CI podia imprimir instruções SQL | Substituição dos logs brutos por pg_isready | Workflow versionado |

A atualização inclui o aviso de severidade alta do parser YAML [GHSA-hwfg-qwvh-2wfx](https://github.com/symfony/symfony/security/advisories/GHSA-hwfg-qwvh-2wfx). A ausência de avisos descreve o resultado da auditoria naquele momento, não uma garantia permanente.

## Contrato HTTP revisado

Login/logout, usuário autenticado, organizações acessíveis, listagem/inclusão/alteração/revogação de vínculos e health usam os contratos documentados em http-api.md, authentication.md e memberships-http.md. A inicialização CSRF é parte do fluxo SPA.

A suíte verifica 401 para ausência de autenticação, 403 para autorização negada, 404 para recurso fora do escopo ou inexistente, 409 para conflito, 419 para CSRF, 422 para validação e 429 com Retry-After para limite. Erros usam Problem Details; erros inesperados retornam mensagem genérica mesmo com debug habilitado. As respostas públicas possuem campos explícitos e não incluem hash, ID de sessão ou trace.

## Contrato de logs

Nunca registrar credenciais, corpos completos, cookies, tokens, Request ou modelos. A filtragem é defesa adicional: remove campos sensíveis, valores conhecidos na requisição atual, hashes reconhecidos e padrões de credenciais em texto. Contextos e extras são percorridos com profundidade limitada; objetos são opacos; exceções preservam classe, mensagem sanitizada e localização, removendo argumentos e objetos do trace.

O logger consulta a requisição atual em cada registro, sem capturar o contexto anterior. Os testes cobrem interpolação, contexto do framework, exceções, objetos, ciclos, emergência e reutilização do logger.

A filtragem cobre os canais Monolog usados pela aplicação. Uma futura implementação de logger externo precisa preservar esse contrato. Texto arbitrário desconhecido fora de campos ou padrões identificáveis não pode ser reconhecido com garantia: a proibição de registrar segredos continua necessária. Logs de banco, proxy e plataforma ficam fora do processador da aplicação e precisam de política própria antes de produção.

## Validação

Executar composer check e composer audit --locked --no-interaction no container da API. Os testes HTTP exercitam a aplicação real com PostgreSQL exclusivo e cookies/CSRF reais; não constituem pentest externo. Nenhuma configuração de exposição local de serviços foi alterada.
