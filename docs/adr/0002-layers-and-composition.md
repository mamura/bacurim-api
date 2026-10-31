# ADR-0002 — Camadas e composição explícita

Status: adotado na fundação (#14–#18).

## Contexto

O framework deve fornecer mecanismos de infraestrutura sem se tornar dependência das regras de negócio. A aplicação ainda não tem casos de uso financeiros que justifiquem abstrações genéricas.

## Decisão

Organizar cada módulo em Domain, Application, Infrastructure e Interfaces, sob `app/Modules/<Module>`, usando o mapeamento PSR-4 existente `App\`. Domain é independente; Application depende de Domain; Infrastructure implementa portas internas; Interfaces chama casos de uso. Bootstrap e providers são o ponto explícito de composição.

Registrar providers de módulo em `bootstrap/providers.php` quando houver dependências reais. Usar injeção por construtor e bindings definidos pelo módulo fornecedor. A comunicação entre módulos usa somente `Application/Contracts/PublicApi`, nas direções do mapa. Não acessar tabelas, ORM ou repositórios internos de outro módulo. Providers não carregam dados ou contexto de tenant ao registrar bindings.

O binding transitório é o padrão. Estado de tenant não pode ser capturado em singleton. Eventos exigem necessidade concreta, consumidor identificado e semântica de transação/entrega documentada. Publicação após commit não garante entrega durável; não há mensageria ou outbox implementadas nesta fundação.

## Consequências

Não é necessário um Composer por módulo, classes base genéricas ou providers vazios. A estrutura inicial conserva quatro diretórios por módulo; subdivisões surgem conforme o código real. As fronteiras são convenções revisadas, ainda sem fiscalização automatizada completa — prevista na #30.

A alternativa de usar Eloquent como entidade de domínio foi rejeitada para as futuras regras financeiras. O skeleton existente não é movido antecipadamente. Um exemplo isolado em fixtures de teste demonstra autoload e composição sem criar regra financeira artificial ou endpoint de produção.

Referências: [camadas](../architecture-layers.md), [estrutura](../modular-structure.md), [integrações](../module-integration.md).
