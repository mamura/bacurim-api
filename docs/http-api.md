# Convenções HTTP da API

## Recursos, versão e operações (#20)

A API usa `/api/v1`. Recursos são substantivos plurais em inglês, em kebab-case quando compostos: `/financial-accounts/{accountId}`. IDs são opacos; rotas identificam recursos e não codificam regras de autorização. Rotas aninhadas só representam pertencimento real e devem validar o mesmo tenant.

| Método | Semântica | Sucesso habitual |
| --- | --- | --- |
| GET | Ler recurso ou coleção; sem mudança de estado de negócio. | 200 |
| POST | Criar recurso ou iniciar uma operação explícita. | 201 com Location quando cria recurso endereçável; 200 para resultado imediato sem novo recurso; 202 apenas se o processamento realmente for assíncrono. |
| PUT | Substituir a representação editável completa de recurso existente. | 200 com recurso; 204 se não há corpo. |
| PATCH | Alterar parcialmente os campos permitidos. | 200 com recurso; 204 se não há corpo. |
| DELETE | Remover conforme o domínio permitir. | 204 sem corpo. |

PUT/DELETE são idempotentes quanto ao efeito, não obrigatoriamente ao status de cada repetição. GET/HEAD não alteram dados de negócio. PATCH só pode ser considerado idempotente quando a operação concreta garantir isso. POST não possui garantia geral de idempotência; operações financeiras repetíveis precisarão de contrato explícito de chave, escopo por tenant e deduplicação antes de serem implementadas. Não há mecanismo genérico de idempotência nesta fundação.

Não usar DELETE para apagar fatos financeiros realizados: cancelamento, estorno e ajuste são operações explícitas de domínio que preservam histórico. URLs de ação devem representar essa intenção, por exemplo `POST /financial-transactions/{id}/reversals`, sem implementar esses endpoints agora.

Erros usuais: 400 entrada malformada; 401 autenticação necessária; 403 operação não autorizada; 404 recurso ausente ou não acessível conforme política de isolamento; 405 método não permitido com Allow; 409 conflito com o estado atual; 415 mídia não suportada; 422 validação semântica/campos inválidos; 429 limite excedido com Retry-After quando aplicável; 500 falha inesperada. Autenticação e rate limiting reais permanecem fora desta etapa.

IDs e pertencimento usam parâmetros de rota. Query string é destinada a paginação, filtros e ordenação, nunca para enviar segredos ou escolher livremente um tenant. O tenant da operação precisa ser conhecido e autorizado conforme a arquitetura.

Filtros e campos ordenáveis devem ser allowlists por endpoint: parâmetros externos não viram nomes de coluna, SQL ou caminhos internos diretamente. Parâmetros desconhecidos/rejeitados devem gerar 422 no endpoint que implementa a consulta; não são ignorados silenciosamente. As convenções serão detalhadas nos contratos de sucesso.

Mudanças compatíveis permanecem em v1; remover/renomear campos ou alterar semântica exige revisão de compatibilidade e futura versão. Não implementar duas versões antes de precisar delas. O health check técnico mantém `GET /api/v1/health` e `{"status":"ok"}`, sem envelope de recurso de negócio.

Referências normativas: HTTP [RFC 9110](https://www.rfc-editor.org/rfc/rfc9110.html) e erros [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457.html). A implementação usa mecanismos nativos do Laravel; as convenções não transformam casos de uso em classes HTTP.

## Respostas de sucesso (#21)

Serializar recursos com `Illuminate\Http\Resources\Json\JsonResource` em `Interfaces/Http/Resources` do módulo, selecionando campos explicitamente. Não retornar modelos Eloquent diretamente nem remover o wrapping global. Usar `Resource::collection(...)` para coleções; não criar envelope paralelo genérico.

Recurso: `{"data":{"id":"1","name":"Example"}}`. Coleção: `{"data":[{"id":"1","name":"Example"}]}`. Coleção vazia: `{"data":[]}`. Criação retorna 201 e Location quando endereçável; atualização retorna 200 com o mesmo recurso ou 204; exclusão retorna 204 sem JSON, inclusive sem `data:null`.

Para paginação, usar `LengthAwarePaginator` com API Resources e estrutura nativa `data`, `links` e `meta`. Links: `first`, `last`, `prev`, `next`, com null quando não aplicável. Meta: `current_page`, `from`, `to`, `last_page`, `path`, `per_page`, `total` e a lista `links` nativa do paginator. Não paginar coleções carregadas integralmente quando houver consulta persistida disponível.

Parâmetros: `page` (inteiro ≥1, padrão 1), `per_page` (inteiro 1–100, padrão 20), `filter[field]=value` e `sort=field,-other_field` (sinal menos para decrescente). Cada endpoint documenta e valida campos, operadores e ordenação padrão. Usar um desempate estável, como ID. Não há parser universal nem endpoint filtrável de negócio nesta etapa. Preservar filtros/ordenação validados nos links com `appends(...)`; não refletir parâmetros arbitrários.

Nomes de propriedades usam snake_case. IDs são strings opacas, booleanos são booleanos JSON e contagens são inteiros. Instantes são strings RFC 3339/ISO 8601 em UTC com sufixo `Z`, por exemplo `2026-10-31T12:00:00Z`. Datas civis sem horário usam `YYYY-MM-DD`, sem conversão implícita de timezone. Ausência de valor usa null conforme contrato, não string vazia por conveniência.

Valores monetários usam decimal em string e moeda explícita, por exemplo `{"amount":"1234.56","currency":"BRL"}`. Não converter a float em nenhuma etapa da serialização ou cálculo. Precisão, escala e arredondamento serão definidos pelo domínio por moeda/operação; não assumir escala 2 universalmente. Percentuais/decimais que exijam precisão também usam strings documentadas. Não criar entidades monetárias artificiais na fundação.

Os contratos são exercitados por `ApiSuccessContractTest` com fixtures, sem criar rotas de produção. O health check é uma exceção técnica explícita ao envelope.

## Tratamento central de exceções (#22)

`App\Http\Responses\ProblemDetails` é registrado no bootstrap do Laravel e atende exceções nos caminhos `/api` e `/api/*`, independentemente de Accept. A resposta usa `application/problem+json` e os membros RFC 9457 `type`, `title`, `status`, `detail`, `instance`. Erros HTTP genéricos usam `type=about:blank` e o título padrão do status. `instance` é o caminho da requisição, sem query string, para não refletir tokens ou filtros sensíveis.

Exemplo: `{"type":"about:blank","title":"Not Found","status":404,"detail":"The requested resource was not found.","instance":"/api/v1/absent"}`.

O status do corpo acompanha o status HTTP. Recursos/modelos ausentes retornam 404, métodos inválidos 405, falta de autenticação 401 e autorização negada 403. Os adaptadores usam as exceções nativas correspondentes. Os cabeçalhos semânticos Allow, Retry-After e WWW-Authenticate são preservados quando fornecidos. Não copiar cabeçalhos de corpo de uma resposta anterior para o problema JSON.

Falhas inesperadas retornam 500 genérico, mesmo com debug ativo na API. Não incluir SQL, valores de configuração, nomes de classe, arquivo ou trace. O pipeline padrão de report do Laravel permanece ativo para registrar a exceção original; o renderer não faz logging duplicado nem suprime erros inesperados. A equipe deve aplicar a política de segredos também às mensagens e aos dados registrados em logs.

A padronização cobre erros tratados pelo Laravel. Falhas geradas antes dele, como proxy indisponível ou corpo rejeitado pelo Nginx, dependem da configuração dessa infraestrutura. Rotas web preservam o tratamento padrão. Controllers da API devem lançar exceções mapeadas em vez de retornar corpos de erro ad hoc; endpoints futuros devem ter seus contratos testados.

## Validação e falhas conhecidas (#23)

Validação de entrada usa Form Requests ou Validator nativos e retorna 422 com `code=validation_failed`, `type=urn:bacurim:problem:validation_failed` e `errors`, um objeto com campo → lista de mensagens. Campos aninhados usam notação com pontos, por exemplo `items.0.label`. A resposta não inclui os valores submetidos. Mensagens são voltadas ao consumidor; a infraestrutura não deve colocar segredos em mensagens customizadas de validação.

Exemplo: `{"type":"urn:bacurim:problem:validation_failed","title":"Unprocessable Content","status":422,"detail":"The submitted data is invalid.","instance":"/api/v1/resources","code":"validation_failed","errors":{"name":["The name field is required."]}}`.

Casos de uso podem sinalizar falhas esperadas por `App\SharedKernel\Application\Exceptions\ApplicationFailure`, com `FailureKind`, `errorCode` estável e `publicMessage` explicitamente seguro para publicação. O tipo é PHP puro, sem Laravel ou status HTTP. O adaptador HTTP mapeia Validation → 422, Conflict → 409, NotFound → 404 e Forbidden → 403. Domain continua com exceções próprias; Application traduz uma violação esperada quando precisar expô-la como contrato da operação. Não envolver indiscriminadamente qualquer Throwable em falha conhecida.

Códigos usam palavras minúsculas com underscore e namespace por pontos, por exemplo `financial.operation_conflict`. Eles identificam a condição, não detalhes da implementação, e permanecem estáveis para decisões do frontend. Cada módulo documentará seus códigos reais; os códigos `example.*` dos testes não são regras financeiras. O tipo Problem Details é `urn:bacurim:problem:<code>`; a URI identifica o problema e não é uma URL de consulta. Mensagens podem evoluir; clientes não devem interpretar texto para identificar falhas.

A falha conhecida só usa a mensagem pública fornecida explicitamente, não uma mensagem arbitrária de exception de infraestrutura. Não usar mensagem pública para inserir SQL, tenant de terceiros, payload ou credenciais. Falhas esperadas não geram logging de erro por padrão. Erros inesperados continuam 500 e reportados pelo Laravel. Campo inválido (422), conflito de estado (409) e falha interna (500) são categorias diferentes.

O contrato de falhas é a primeira abstração concreta do Shared Kernel da aplicação: existe para desacoplar as falhas conhecidas dos adaptadores HTTP e será reutilizável por casos de uso dos módulos. Não introduz identidade de tenant, entidades financeiras ou repositórios genéricos no núcleo compartilhado.

## Guia de reutilização e exemplos (#24)

Novos módulos devem usar Resources em Interfaces, Form Requests para entrada e contratos de falha de Application quando houver uma condição esperada. Não mover classes HTTP para Domain/Application. O renderer e os middlewares são adaptadores globais de HTTP, fora dos módulos de negócio.

`EnsureValidJson` rejeita corpo JSON sintaticamente inválido com 400 antes do handler da rota. Corpo ausente segue as regras de validação da operação. Mídia não suportada deve ser rejeitada com 415 pelo endpoint que exigir um tipo específico; a fundação não proíbe genericamente outras mídias. `NormalizeApiErrors` cobre respostas 4xx/5xx diretas e HttpResponseException que possam passar fora do callback de exceções, descartando corpos ad hoc e preservando cabeçalhos semânticos. Erros que já são Problem Details não são reempacotados.

Exemplo real de requisição técnica:

```bash
curl -i http://api-bacurim.mamura.test/api/v1/health
# HTTP 200, application/json, {"status":"ok"}
curl -i -H 'Accept: text/html' http://api-bacurim.mamura.test/api/v1/absent
# HTTP 404, application/problem+json, membros type/title/status/detail/instance
curl -i -X POST http://api-bacurim.mamura.test/api/v1/health
# HTTP 405, Allow: GET, HEAD, application/problem+json
```

Exemplo conceitual de coleção paginada (nenhum endpoint financeiro é criado por esta documentação):

```json
{
  "data": [{"id":"1","name":"Example"}],
  "links": {"first":"/api/v1/resources?page=1","last":"/api/v1/resources?page=2","prev":null,"next":"/api/v1/resources?page=2"},
  "meta": {"current_page":1,"from":1,"last_page":2,"path":"/api/v1/resources","per_page":1,"to":1,"total":2,"links":[]}
}
```

O exemplo abrevia a lista interna `meta.links`; a serialização real é a do paginator nativo e inclui os links de navegação. Uma consulta documentada poderia usar `?page=1&per_page=20&filter[status]=pending&sort=-date,id`; campos e operadores só estarão disponíveis quando o respectivo endpoint os implementar e validar.

Exemplo de conflito conhecido:

```json
{
  "type":"urn:bacurim:problem:example.state_conflict",
  "title":"Conflict",
  "status":409,
  "detail":"The operation is incompatible with the current state.",
  "instance":"/api/v1/resources/1",
  "code":"example.state_conflict"
}
```

Os textos genéricos do adaptador são em inglês. Mensagens de campos seguem o locale configurado no Laravel; não há negociação de idioma por Accept-Language nesta etapa. Identificadores e estrutura são estáveis independentemente do texto.

## Matriz de contratos testados

| Suíte | Contratos |
| --- | --- |
| `ApiSuccessContractTest` | 201/Location; recurso e coleção com data; coleção vazia; paginação com links/meta; 204 vazio; instante em UTC e preservação de decimal/ID acima da precisão segura de ponto flutuante. |
| `ApiProblemContractTest` | 404 mesmo solicitando HTML; instance sem query; 405/Allow; 429/Retry-After; logging de erro inesperado; proteção de mensagens internas; preservação do comportamento web. |
| `ApiValidationContractTest` | 422 por campo, incluindo campos aninhados; código e mensagem públicos de conflito; falhas esperadas sem logging de erro. |
| `ApiExtendedContractTest` | Todas as categorias de falha de aplicação; autenticação/autorização/modelo ausente; 400, 415 e 503; resposta direta encapsulada; JSON inválido; GET/PUT/PATCH; debug desligado/produção; WWW-Authenticate. |
| `ApiHealthTest` | Contrato técnico 200 e erros básicos em rotas reais. |

As rotas `/_contract` só são registradas pelos testes. Não aparecem em `routes/` nem expõem exemplos ao consumidor real. Os testes de autenticação lançam exceções nativas para verificar o contrato; não implementam login, permissões ou rate limiting.

Na raiz do workspace:

```bash
docker compose exec api composer test
docker compose exec api vendor/bin/pint --test
docker compose exec api composer dump-autoload --optimize --strict-psr
docker compose exec api php artisan route:list
```

Fontes: [RFC 9457](https://www.rfc-editor.org/rfc/rfc9457.html) e [Laravel API Resources](https://laravel.com/docs/12.x/eloquent-resources). O contrato usa wrapping e paginação nativos; os campos de negócio futuros serão documentados/testados em cada módulo, sem OpenAPI fictício de endpoints inexistentes.
