# ADR-0003 — Contexto explícito e núcleo compartilhado mínimo

Status: princípios adotados na fundação (#17–#18); mecanismo de tenancy pendente de implementação.

## Contexto

Domain Model §5 estabelece um tenant por empresa no MVP e usuários pertencentes a um único tenant. BR-071 a BR-075 exigem isolamento inclusive quando o identificador de um recurso é conhecido. Empresa e tenant não são conceitos equivalentes.

## Decisão

Operações empresariais recebem explicitamente contexto com tenant, empresa e ator quando aplicável. A borda estabelece origem e autorização; construir um DTO não prova acesso. Portas e adaptadores preservam escopo e verificam pertencimento das referências. Não existe tenant padrão, identidade global ou alternância entre tenants no MVP.

Compartilhar apenas abstrações com consumidores e semântica comum demonstráveis. Identificadores/contexto são candidatos mínimos; entidades financeiras, ORM, repositórios genéricos e utilitários sem dono não integram o Shared Kernel. Não criar tipos de produção sem consumidores reais.

Cache, locks, idempotência, importações, exportações e jobs mantêm o escopo de tenant. O prefixo global do Redis não fornece esse isolamento. Workers futuros não podem carregar estado residual de outro tenant.

## Consequências

Autenticação, resolução/autorização real e isolamento efetivo serão implementados no API-M1. A fundação não afirma que o skeleton atual já é multitenant. Banco por tenant, schema por tenant ou tabelas compartilhadas continuam decisões abertas para a arquitetura de dados; não inferir a estratégia do PostgreSQL único no ambiente local.

A alternativa de usar contexto global mutável foi rejeitada por dependência implícita e possibilidade de vazamento em processos persistentes. Uma futura conveniência scoped em adaptadores não substitui o contexto explícito de Application.

Referência: [contratos e princípios de tenancy](../shared-kernel-and-tenancy.md).
