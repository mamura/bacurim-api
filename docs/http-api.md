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
