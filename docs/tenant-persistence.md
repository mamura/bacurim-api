# Persistência com escopo de organização

## Regras obrigatórias

As tabelas financeiras futuras usam organization_id NOT NULL como FK restritiva para organizations.id. UUID público é exposto na API; IDs internos não conferem autoridade. Não há dados financeiros implementados nesta milestone.

As portas Application/Contracts/Persistence de Financial, DataImport, Reporting e Forecasting devem receber TenantContext obrigatório como primeiro argumento de cada operação. O verificador arquitetural rejeita interfaces sem esse parâmetro, nullable ou com valor padrão. Identificador de recurso é um argumento adicional; não existe find global por conveniência. Casos de uso revalidam contexto e capacidade antes de chamar a porta.

Repositórios concretos aplicam organization_id em SELECTs, joins, agregações, UPDATEs e exclusão lógica. Buscam por organização + UUID, sem carregar primeiro um recurso global. Recursos externos ou arquivados retornam NotFound. Grupos OR precisam estar entre parênteses sob o filtro organizacional. SQL bruto e exportações têm as mesmas obrigações; o verificador de assinaturas não prova o SQL.

Criação deriva organization_id do contexto autorizado, nunca de atributos do cliente. Atualização só aceita campos editáveis do próprio domínio e nunca organization_id, public_id ou PK por atribuição em massa. Não oferecer operação de transferência de proprietário organizacional. Atualizações usam condição organizacional, identidade e versão esperada quando aplicável; exclusão lógica preserva associação e histórico, conforme as regras do domínio.

FKs para registros da organização devem ser compostas (organization_id, referenced_id), referenciando chave única equivalente. Isso protege relações cruzadas inclusive quando um filtro da aplicação falha. Índices iniciam por organization_id e seguem os filtros/ordenação reais. Política de unicidade deve indicar se vale por organização e se registros arquivados ainda reservam o valor. Relações com identidades globais continuam sob regras próprias, sem fingir que User é tenant-owned.

## Exemplo executável

ScopedPersistenceTest cria uma tabela de probes exclusivamente no banco de testes e demonstra criação pelo contexto, leitura, alteração, exclusão lógica, proibição de consultas sem contexto e FK composta entre organizações. ScopedProbeRepository e ProbeSchema vivem em Tests/Support, não são serviços de produção nem repositórios genéricos de negócio. As fixtures não criam migrations ou rotas financeiras.

A implementação de cada futuro repositório financeiro deverá incluir seus próprios testes de SQL e domínio. Global Scope pode ser defesa adicional, mas não substitui assinaturas explícitas, autorização atual ou FKs. RLS permanece opcional futuro. Nenhuma abstração genérica de CRUD/ORM foi adicionada à aplicação.
