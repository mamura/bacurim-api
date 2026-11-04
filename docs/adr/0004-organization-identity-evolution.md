# ADR-0004 — Organização e evolução da identidade na API-M1

Status: adotado para organizações (#39); contratos de identidade/memberships/tenant aprovados no planejamento, pendentes nas respectivas issues.

## Contexto

O planejamento API-M1 (#39, #45, #52, #59 e #66) evolui a premissa do Domain Model externo e do ADR-0003: identidade passa a ser global, com vínculos de acesso a uma ou mais organizações. A autorização continua específica à organização. Esta decisão substitui apenas as premissas de usuário restrito a um tenant e ausência de identidade global do ADR-0003; contexto explícito, isolamento e núcleo compartilhado mínimo continuam válidos. Os documentos externos de produto não foram alterados.

## Decisão

Organizations possui Organization, ciclo de vida e persistência. Uma organização não exige owner ou usuário na criação interna. IdentityAccess continuará responsável por identidade, credenciais, autenticação e memberships (#45/#59). Os papéis serão associados ao vínculo, não globalmente ao usuário.

A #66 prevê organização como referência explícita do contexto em PostgreSQL compartilhado. Não criar tabela Tenant redundante nesta #39. Organização é a entidade de negócio; tenant continua sendo o conceito de fronteira de isolamento. O contrato técnico final de contexto/autorização pertence à #66 e não está implementado aqui.

Organization usa UUID v4 público e bigint interno. UUID é opaco e único no banco, sem conceder acesso. O documento é opcional e não globalmente único. Entidade imutável, portas de Application e modelo Eloquent separado preservam a arquitetura. Timestamps explícitos em UTC e versão inteira com lock transacional protegem atualizações contra perda de dados.

## Consequências

As capacidades atuais são internas. Não expor os casos de uso sem autorização real e contrato HTTP. Não assumir isolamento multi-tenant, autenticação ou memberships implementados pela presença de organizations. Os demais módulos continuam sem implementação de negócio. Novas integrações devem criar contratos públicos específicos, sem importar repositórios ou entidades internas de Organizations.
