# ADR-0004 — Organização e evolução da identidade na API-M1

Status: adotado para organizações (#39), identidades (#45), autenticação (#52) e memberships (#59); contexto explícito de tenant implementado na #66 conforme ADR-0005.

## Contexto

O planejamento API-M1 (#39, #45, #52, #59 e #66) evolui a premissa do Domain Model externo e do ADR-0003: identidade passa a ser global, com vínculos de acesso a uma ou mais organizações. A autorização continua específica à organização. Esta decisão substitui apenas as premissas de usuário restrito a um tenant e ausência de identidade global do ADR-0003; contexto explícito, isolamento e núcleo compartilhado mínimo continuam válidos. Os documentos externos de produto não foram alterados.

## Decisão

Organizations possui Organization, ciclo de vida e persistência. Uma organização não exige owner ou usuário na criação interna. IdentityAccess continuará responsável por identidade, credenciais, autenticação e memberships (#45/#59). Os papéis serão associados ao vínculo, não globalmente ao usuário.

A #66 prevê organização como referência explícita do contexto em PostgreSQL compartilhado. Não criar tabela Tenant redundante nesta #39. Organização é a entidade de negócio; tenant continua sendo o conceito de fronteira de isolamento. O contrato técnico de contexto/autorização foi implementado na #66 e está detalhado no ADR-0005.

Organization usa UUID v4 público e bigint interno. UUID é opaco e único no banco, sem conceder acesso. O documento é opcional e não globalmente único. Entidade imutável, portas de Application e modelo Eloquent separado preservam a arquitetura. Timestamps explícitos em UTC e versão inteira com lock transacional protegem atualizações contra perda de dados.

## Consequências

Os casos de uso de organizações/identidades continuam internos. Autenticação e memberships agora possuem endpoints autorizados, com organização explícita no gerenciamento de vínculos (#52/#59). A #66 adiciona TenantContext explícito, revalidação de autorização e contratos de isolamento; funcionalidades financeiras continuam futuras. Os demais módulos continuam sem implementação de negócio. Novas integrações devem criar contratos públicos específicos, sem importar repositórios ou entidades internas de Organizations.
