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
- [Shared Kernel e tenancy](shared-kernel-and-tenancy.md): contexto explícito e regras de isolamento; resolução real ainda não implementada.

## Decisões e contratos

- [Decisões tecnológicas iniciais](initial-technical-decisions.md).
- [ADR-0001: monólito modular](adr/0001-modular-monolith.md).
- [ADR-0002: camadas e composição](adr/0002-layers-and-composition.md).
- [ADR-0003: contexto de tenant](adr/0003-explicit-tenant-context.md).
- [Contratos HTTP](http-api.md): REST, respostas, paginação, precisão, erros e exemplos.

Os documentos de produto externos estão identificados e sintetizados no mapa dos contextos. Este repositório contém a referência técnica necessária para seguir o desenvolvimento, mas não os arquivos de infraestrutura do workspace nem credenciais locais.
