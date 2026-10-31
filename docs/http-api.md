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
