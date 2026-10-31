# Shared Kernel e limites de tenancy

Definição da issue [#17](https://github.com/mamura/bacurim-api/issues/17). Complementa o [mapa dos contextos](context-map.md), as [camadas](architecture-layers.md) e as [convenções de integração](module-integration.md). Define contratos e princípios, sem implementar autenticação, autorização real, resolução de tenants ou persistência multitenant.

## Fronteira do MVP

Conforme Domain Model §5 e Business Rules BR-071 a BR-075:

- Um tenant delimita os dados e as operações de exatamente uma empresa no MVP.
- Empresa é entidade de negócio; tenant é fronteira de segurança. Seus identificadores são conceitos distintos, mesmo com relação 1:1.
- Cada usuário pertence a um único tenant. Vários usuários podem acessar a mesma empresa dentro dessa fronteira.
- Não existe identidade global alternando entre tenants. Consultor é usuário autorizado no tenant correspondente, sem carteira centralizada.
- Dados e relações financeiras não atravessam tenants implicitamente. Conhecer um identificador não concede acesso.

A expressão empresa ativa significa a empresa associada ao tenant autorizado para a operação. Não significa implementar um seletor de empresa ou aceitar qualquer empresa enviada pelo cliente.

## O que pode ser compartilhado

O Shared Kernel é um conjunto pequeno de conceitos com semântica idêntica, necessários a mais de um módulo. Não é um módulo de negócio, um atalho de comunicação ou um lugar para código sem dono.

| Conceito | Decisão |
| --- | --- |
| Referência de tenant | Conceito transversal necessário para delimitar operações e contratos públicos. Identificador opaco; formato e geração serão definidos no API-M1. |
| Referência de empresa | Necessária para tornar explícita a empresa do contexto e preservar a distinção em relação ao tenant. Não compartilha a entidade Empresa. |
| Referência de ator | Metadado explícito de operações rastreáveis; não transporta usuário ORM, credenciais ou permissões. |
| Contexto da operação no tenant | Contrato de dados imutáveis com tenant, empresa e ator quando aplicável. Não é um serviço global para descobrir a requisição corrente. |
| Dinheiro, moeda e período | Permanecem no módulo que precisar de suas regras. Compartilhar somente após demonstrar semântica e uso comuns. |
| Entidades, agregados, categorias e regras financeiras | Permanecem em Financial. Não entram no Shared Kernel. |
| Repositórios genéricos, BaseService e helpers | Não criar por conveniência de reutilização. |
| Autenticação e resolução do tenant | Mecanismos dos adaptadores e módulos de acesso, não regras do Shared Kernel. |

Localização reservada para abstrações concretas justificadas: `App\SharedKernel\Domain` para valores puros e `App\SharedKernel\Application` para contexto/contratos de aplicação. Nenhuma dessas camadas depende de Laravel, HTTP, ORM ou de um módulo. Os módulos podem depender desse núcleo mínimo; o núcleo não depende deles.

Ainda não há consumidor de negócio implementado. Portanto, esta issue define os contratos abaixo sem criar diretórios vazios, classes sem uso ou decidir UUID/ULID por antecipação. Ao introduzir o primeiro fluxo real, criar apenas os tipos necessários e seus testes.

## Contrato do contexto

O contrato semântico de `TenantContext`, a ser materializado como dado imutável de Application quando houver consumidor real, contém:

| Campo | Obrigatoriedade | Semântica |
| --- | --- | --- |
| `tenantId` | Sempre | Tenant autorizado da operação; não vazio e sem fallback global. |
| `companyId` | Sempre | Empresa do tenant; a relação deve ter sido validada, não inferida pela igualdade dos identificadores. |
| `actorId` | Obrigatório em operações humanas | Usuário autenticado/autorizado nesse tenant; obrigatório para alterações financeiras rastreáveis. |

A validade estrutural de um contexto não prova autorização. Instanciar um DTO com identificadores fornecidos pelo cliente não concede acesso. A borda deve estabelecer a origem e autorização do contexto, e o caso de uso/adaptador deve verificar pertencimento das referências utilizadas.

Para futuras operações de sistema, definir explicitamente identidade técnica, motivo, escopo e autorização. Não usar `actorId=null` como bypass genérico ou inventar um usuário administrador global. Essa modalidade não é implementada nesta fundação.

Os casos de uso financeiros e seus contratos de persistência/leitura receberão `TenantContext` explicitamente, preferencialmente como primeiro argumento ou campo obrigatório da entrada. Contratos entre módulos transportam o mesmo escopo, sem reconstituí-lo a partir de estado ambiente. Identificadores de recursos são parâmetros adicionais e nunca substituem o contexto.

Não haverá `Tenant::current()`, singleton mutável, tenant padrão ou seleção silenciosa do primeiro tenant. Contexto ausente, contraditório, não autorizado ou incompatível com uma referência deve interromper a operação antes de produzir alterações. A tradução HTTP das falhas pertence às convenções da API, sem revelar dados do tenant de terceiros.

## Origem e propagação

1. A entrada HTTP, CLI ou job estabelece identidade e autorização pelo mecanismo adequado ao fluxo. Resolver isso de verdade pertence ao API-M1.
2. Organizations é dono da relação tenant–empresa; IdentityAccess é dono da identidade e da autorização dentro dessa fronteira.
3. A composição de entrada constrói o contexto com a relação validada e chama o caso de uso. Não confiar apenas em headers, subdomínios, payload, parâmetros de rota ou valores ocultos de formulário.
4. Application recebe o contexto explicitamente e o propaga às portas e integrações. Domain valida invariantes de pertencimento quando uma operação relaciona conceitos do tenant.
5. Infrastructure aplica o escopo em leitura e escrita, sem depender de um contexto global implícito.

Um binding `scoped` pode servir de conveniência para adaptadores quando necessário, conforme a #16, mas não substitui a passagem explícita em Application. Nunca guardar contexto em singleton nem capturá-lo em serviços de vida mais longa.

## Operações que exigem contexto

| Operação | Requisito |
| --- | --- |
| Consultar/criar/alterar contas, categorias, eventos e compromissos | Contexto autorizado e pertencimento de todas as referências. |
| Realizar, estornar, ajustar ou cancelar fatos financeiros | Mesmo tenant em todos os registros; ator e rastreabilidade preservados. |
| Transferir entre contas | Origem e destino no mesmo tenant/empresa; saldo e histórico sujeitos às regras de Financial. |
| Preparar, revisar e confirmar importações | Arquivo, sessão de importação, dados preparados e confirmação ligados ao mesmo tenant. Revalidar na confirmação. |
| Relatórios, indicadores, projeções e exportações | Escopo obrigatório mesmo em consultas agregadas, arquivos gerados e links para download. |
| Cache, jobs, comandos e eventos | Escopo explícito e propagado; não reutilizar contexto residual de outra execução. |
| Gestão de usuários/configurações da empresa | Operação autorizada dentro do tenant, conforme as regras de Organizations/IdentityAccess. |

Exceções técnicas sem dados empresariais, como o health check de bootstrap atual, não exigem tenant. Operações de criação inicial de tenant/empresa ainda não possuem um tenant existente para consultar; deverão ter fluxo específico e autorização explícita no API-M1, sem reutilizar essa exceção para ler dados de tenants existentes.

## Persistência e proteção contra acesso cruzado

A estratégia técnica entre banco por tenant, schema por tenant ou tabelas compartilhadas ainda não foi escolhida. O PostgreSQL único do ambiente local não decide essa arquitetura.

Independentemente da estratégia:

- Consultar um recurso pelo identificador dentro do escopo do tenant, nunca carregar globalmente e depois assumir acesso.
- Validar que tenant/empresa das referências coincidem, inclusive em atualizações parciais, operações em lote e transferências.
- Não aceitar mudança de tenant/empresa por atribuição em massa, campos de edição ou atualização de uma entidade financeira existente.
- Não usar consultas agregadas, joins, SQL bruto, tarefas agendadas ou exportação como exceção ao isolamento.
- A autorização da requisição não dispensa o escopo de persistência. Scopes ORM, se adotados, serão uma defesa adicional, não autorização completa.
- Definir restrições de integridade, índices e políticas de conexão apropriadas na futura arquitetura de dados. Não prometer isolamento implementado antes dessas decisões e testes.

## Cache e execução em segundo plano

O prefixo global `bacurim_api_...` separa aplicações, mas não separa tenants. Chaves de dados empresariais devem incluir `tenantId` e dimensões relevantes da operação. Invalidação, locks e idempotência também precisam desse escopo; não usar uma mesma chave para resultados de empresas distintas.

Jobs/eventos carregam identificadores mínimos de tenant, empresa e ator, além dos dados necessários ao contrato. Não serializam Request, credenciais ou modelo ORM como prova de autorização. A execução reconstrói e revalida o contexto quando necessário; contexto de HTTP não permanece autorizado indefinidamente.

Workers persistentes precisam limpar qualquer estado por execução e não compartilhar serviços com tenant capturado. Conexões, schema selecionado e caches locais deverão ser restaurados de acordo com a estratégia de tenancy escolhida. Não há worker ou mecanismo desse tipo implementado neste milestone.

## Critérios para ampliar o Shared Kernel

Antes de mover uma abstração para o núcleo compartilhado:

1. Identificar consumidores concretos e a mesma semântica em todos eles.
2. Confirmar que não é entidade ou regra pertencente a um módulo.
3. Preservar independência de framework e de módulos.
4. Definir invariantes, compatibilidade e testes da abstração.
5. Registrar a decisão e verificar que não surgiu dependência circular.

Sem necessidade demonstrada, manter o conceito local ou usar tipos públicos próprios nas integrações.

## Validação futura

Ao implementar tenancy, testar pelo menos: dois tenants com referências distintas; tentativa de leitura/alteração cruzada por ID conhecido; relações entre registros de tenants diferentes; contexto ausente; importação/exportação; cache e locks; execução sucessiva de jobs de tenants diferentes; ator sem autorização e alteração de permissões durante o processamento.

Esta #17 define as fronteiras e o contrato semântico, mas não torna a API atual multitenant. A #18 valida a organização e a resolução de dependências por exemplo técnico; os fluxos de autenticação e isolamento efetivo pertencem ao API-M1 e às implementações subsequentes.

Voltar ao [README](../README.md).
