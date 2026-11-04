# Documentação técnica

## Executar e contribuir

- [README principal](../README.md): requisitos, fluxo local e comandos principais.
- [Ambiente Docker](development-environment.md): estrutura externa à API, primeira instalação, serviços, persistência, Artisan/Composer e troubleshooting.
- [Contribuição e Definition of Done](../CONTRIBUTING.md): branches, commits, PRs, revisão e conclusão de issues.
- [Testes e qualidade](testing-and-quality.md): PHPUnit, banco exclusivo, isolamento, Pint, PHPStan/Larastan, regras arquiteturais e comandos CI.
- [Integração contínua](continuous-integration.md): workflow, serviços, verificações e validação de proteção de branch.

## Arquitetura

- [Índice da arquitetura](architecture.md): diagramas e exemplo técnico executável.
- [Contextos](context-map.md): responsabilidades, limites e dependências dos seis módulos.
- [Camadas](architecture-layers.md): Domain, Application, Infrastructure e Interfaces.
- [Estrutura modular](modular-structure.md): namespaces, diretórios e expansão.
- [Integrações e providers](module-integration.md): contratos públicos, bindings e eventos.
- [Shared Kernel e tenancy](shared-kernel-and-tenancy.md): contexto explícito e regras atuais de isolamento.

## Domínios implementados

- [Organizações](organizations.md): entidade, invariantes, schema PostgreSQL, persistência, casos de uso internos e limites atuais.

- [Identidade e usuários](identity.md): domínio global, e-mail canônico, credenciais, persistência e casos de uso internos.

- [Autenticação e sessões](authentication.md): Sanctum, login/logout/me, cookies, CSRF/CORS, limites e integração com Next.js.

- [Memberships](memberships.md): domínio, persistência, ciclo de vida, casos de uso e consistência transacional.
- [Matriz de permissões](memberships-authorization.md): Owner, Admin, Member e Viewer por organização.
- [API de membros](memberships-http.md): endpoints, entradas, respostas, CSRF e autorização.

- [Contexto de tenant](tenant-context.md): rota, autorização atual e passagem aos casos de uso.
- [Persistência isolada](tenant-persistence.md): consultas/escritas com escopo e integridade PostgreSQL.
- [Jobs por organização](tenant-jobs.md): reconstrução/revalidação sem sessão HTTP.
- [Segurança de tenant](tenant-security.md): controles, evidências e limites.

## Decisões e contratos

- [Decisões tecnológicas iniciais](initial-technical-decisions.md).
- [ADR-0001: monólito modular](adr/0001-modular-monolith.md).
- [ADR-0002: camadas e composição](adr/0002-layers-and-composition.md).
- [ADR-0003: contexto de tenant](adr/0003-explicit-tenant-context.md).
- [ADR-0004: organização e evolução da identidade](adr/0004-organization-identity-evolution.md).
- [ADR-0005: tenant explícito e PostgreSQL compartilhado](adr/0005-tenant-context-and-shared-postgresql.md).
- [Contratos HTTP](http-api.md): REST, respostas, paginação, precisão, erros e exemplos.

Os documentos de produto externos estão identificados e sintetizados no mapa dos contextos. Este repositório contém a referência técnica necessária para seguir o desenvolvimento, mas não os arquivos de infraestrutura do workspace nem credenciais locais.
