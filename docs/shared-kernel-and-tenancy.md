# Shared Kernel e tenancy

As fronteiras originalmente planejadas na #17 foram materializadas na #66. As premissas históricas de usuário restrito a um tenant foram substituídas pelos [ADRs 0004](adr/0004-organization-identity-evolution.md) e [0005](adr/0005-tenant-context-and-shared-postgresql.md).

## Fronteira atual

A identidade é global e possui memberships em múltiplas organizações, com papéis independentes. A organização define a fronteira de tenant no MVP. PostgreSQL é compartilhado e organization_id identifica o proprietário; não há tabela Tenant redundante ou segundo ID tenant/empresa.

TenantContext vive em SharedKernel/Application como valor imutável independente de framework/módulos. Contém organização interna/pública, ator e versões do usuário/vínculo. Não contém papel global, credenciais, Request, Session ou ORM. Domain permanece puro. Construir o DTO não concede acesso: a identidade vem da borda confiável e as políticas revalidam estado/permissão.

Não existe tenant padrão, Tenant::current(), singleton mutável ou descoberta de organização em sessão. A rota informa a organização; Application recebe contexto obrigatório e repassa o escopo às portas. O atributo temporário da requisição é removido em finally. Endpoints globais não recebem contexto.

## Núcleo mínimo

Contexto e falhas de aplicação são os conceitos transversais concretos. Entidades financeiras, dinheiro, moeda e períodos permanecem em seus módulos até haver semântica comum demonstrada. SharedKernel não depende de módulos/framework, não hospeda repositórios genéricos, BaseService ou autenticação. IdentityAccess possui a autorização; Organizations publica referência e situação da organização.

## Persistência e execução

Tabelas tenant-owned exigem organization_id obrigatório e FKs/índices apropriados. Consultas/comandos incluem organização; criação deriva o proprietário do contexto e edição não o transfere. Referências organizacionais usam FKs compostas. Scopes ORM são complementares; RLS não faz parte desta entrega.

Jobs carregam identificadores mínimos de organização/ator, revalidam acesso e recebem contexto local ao callback. Não há contexto residual no runner entre execuções, inclusive após falha. Cache, locks e idempotência empresariais incluem organização e dimensões relevantes; prefixo global não separa tenants. Operações técnicas sem ator precisam de política explícita futura.

A base integra a gestão real de membros e tem provas representativas de persistência/HTTP/jobs. Não há funcionalidades financeiras; implementações futuras devem preservar esses contratos com testes próprios.

Guias: [contexto](tenant-context.md), [persistência](tenant-persistence.md), [jobs](tenant-jobs.md), [segurança](tenant-security.md), [camadas](architecture-layers.md) e [integrações](module-integration.md).
