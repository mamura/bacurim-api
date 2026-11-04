# Garantias e limites do isolamento (#66/#72)

## Controles implementados

| Fronteira | Controle | Evidência executável |
| --- | --- | --- |
| HTTP | Organização exclusivamente na rota após sessão válida; organização/usuário/vínculo ativos | TenantResolutionTest |
| Requisição | Atributo temporário removido em finally, sem contexto no container/sessão | TenantResolutionTest, TenantIsolationApiTest |
| Application | Contexto obrigatório, revalidação de IDs/versões/permissão | TenantApplicationTest |
| Papéis | Matriz única MembershipPolicy e vocabulário público equivalente | MembershipPolicyTest, TenantPublicContractTest |
| Recursos | Consulta SQL por organização + UUID, recurso externo é 404 | MembershipRepositoryTest, TenantIsolationApiTest |
| Criação/alteração | Proprietário deriva do contexto, sem transferência organizacional | ScopedPersistenceTest, TenantIsolationApiTest |
| Relações | FK organizacional não nula e referência composta | ScopedPersistenceTest |
| Concorrência | Locks/versões e preservação do último Owner | MembershipConcurrencyTest |
| Jobs | Contexto reconstruído/autorizado na execução, sem resolver identidade HTTP | TenantJobIsolationTest |
| Arquitetura | Camadas puras, contratos públicos e contexto obrigatório nas portas futuras tenant-owned | ModuleBoundariesTest |

## Continuidade

Casos dependentes de tenant recebem contexto como primeiro argumento; recurso vem depois. A borda confiável autentica o ator e informa a organização. DTO estrutural não comprova autenticação; TenantAuthorization revalida o snapshot e a capacidade atual. Queries com IDs externos continuam limitadas à organização.

Mudanças críticas bloqueiam organização antes dos usuários em ordem estável, revalidam acesso e escrevem na mesma transação. Não transportar ORM, sessão ou Request entre módulos/jobs nem criar singleton com tenant. Efeitos externos futuros precisam de idempotência e pós-commit apropriados.

Erros RFC 9457: 401 para sessão inválida, 403 para contexto/permissão negados, 404 para organização inexistente ou recurso fora do escopo, 422 para entradas/queries inválidas, 409 para conflitos de estado/versão. Nenhum erro inclui registro externo, SQL ou credenciais. Endpoints globais não selecionam silenciosamente organização.

## Alcance da entrega

Contexto/autorização integra a gestão real de memberships. Recursos tenant-owned são demonstrados por fixtures temporárias e rotas exclusivamente de testes, sem tabelas ou funcionalidades financeiras de produção. Cada futuro repositório financeiro deve aplicar os contratos e provar seu próprio SQL/invariantes. A regra de assinaturas não analisa semanticamente SQL.

A estratégia é isolamento lógico em PostgreSQL compartilhado. Não foram introduzidos RLS, schemas/bancos separados, migração entre organizações, administrador global ou bypass de jobs sem ator. Não há seletor persistente ou worker financeiro configurado. Identidades globais/onboarding mantêm contratos próprios sem acesso implícito a organizações existentes.

Referências: [ADR-0005](adr/0005-tenant-context-and-shared-postgresql.md), [contexto](tenant-context.md), [persistência](tenant-persistence.md), [jobs](tenant-jobs.md).
