# Critérios de segurança e liberação da API-M1 (#79)

Esta referência consolida a validação da [#73](https://github.com/mamura/bacurim-api/issues/73). Os controles são da aplicação atual; recursos financeiros foram exercitados por fixtures de contrato e ainda não são funcionalidades de produto.

## Matriz efetivamente implementada

Todas as permissões exigem usuário, organização e vínculo ativos, contexto correspondente e versões atuais.

| Operação | Owner | Admin | Member | Viewer |
| --- | --- | --- | --- | --- |
| Listar organizações do próprio usuário autenticado | Próprias | Próprias | Próprias | Próprias |
| Listar membros no tenant | Sim | Sim | Não | Não |
| Adicionar/alterar/reativar/revogar Member, Viewer ou Admin | Sim | Sim | Não | Não |
| Conceder Owner ou gerenciar vínculo Owner | Sim | Não | Não | Não |
| Remover/rebaixar último Owner efetivo | Não | Não | Não | Não |
| Capacidade de leitura financeira | Sim | Sim | Sim | Sim |
| Capacidade de escrita financeira | Sim | Sim | Sim | Não |
| Capacidade de configurações operacionais | Sim | Sim | Não | Não |
| Capacidade de responsabilidade/propriedade | Sim | Não | Não | Não |

As quatro últimas linhas são capacidades da política, não endpoints financeiros ou de configuração disponíveis. Transferência automatizada de propriedade não está implementada. Mudanças de papéis usam versão explícita e autorização revalidada na transação. Papéis são por organização, nunca globais. Cadastro interno de organização cria o primeiro Owner atomicamente e não concede acesso a organização existente.

## Evidências e isolamento

| Controle | Regressões principais |
| --- | --- |
| Sessão real, login/logout, CSRF, cookies, origem, limite, expiração Redis e adulteração | Integration/IdentityAccess e Security/AuthenticationSecurityTest |
| Matriz, Admin limitado, autoelevação, último Owner, versões e ausência de efeitos negados | MembershipPolicyTest, MembershipSecurityTest, RoleSecurityTest, ManageOrganizationMembersTest |
| Escopo SQL, UUID estrangeiro, integridade de referências e operações negadas | ScopedPersistenceTest, TenantIsolationApiTest, TenantIsolationContractTest |
| Tenant Context atual, troca entre organizações e limpeza após erro | TenantApplicationTest, TenantResolutionTest, TenantJobIsolationTest |
| Lock real com dois processos/conexões PostgreSQL | MembershipConcurrencyTest |
| Erros sem segredos, cabeçalhos e logs sanitizados | ApiProblem/Validation/ExtendedContractTest, HttpSecurityAuditTest, LogRedactionTest |
| Dependências e limites dos módulos/camadas | Composer audit, PHPStan e ModuleBoundariesTest |

Tenant Context é explícito e imutável. Recursos são buscados/alterados dentro da organização antes de locking; o cliente não define ownership. Chaves estrangeiras compostas protegem referências entre organizações. Autorização verifica fatos atuais; mudanças de usuário/vínculo invalidam contextos anteriores. Jobs carregam identificadores, reconstroem o contexto e autorizam na execução, sem herdar sessão ou tenant global.

Os testes usam PostgreSQL exclusivo e Redis DB15 exclusivo para sessões reais; não substituem persistência por SQLite ou autorização por mocks. A suíte de concorrência cria processos apenas para cenários coordenados, não habilita execução paralela geral do PHPUnit.

Referências detalhadas: [sessões](authentication.md), [papéis](memberships-authorization.md), [tenant](tenant-context.md), [persistência](tenant-persistence.md), [jobs](tenant-jobs.md), [contrato para novos módulos](security-regression-contract.md), [etapas de validação](security-validation.md) e [auditoria HTTP](security-http-audit.md).

## Gate de CI

composer check valida Composer, estilo, análise estática e toda a suíte em duas partições disjuntas: composer test:security executa o grupo crítico security; composer test:other executa os demais testes, incluindo arquitetura. composer test continua executando tudo em um único processo quando necessário. Ambos os grupos são obrigatórios no mesmo job Quality, sem continue-on-error.

O CI também executa composer audit --locked --no-interaction: aviso não ignorado impede aprovação. Não há allowlist de avisos para esta entrega. Dependências vêm do lock; ações estão fixadas por SHA. Revisar o resultado do commit atual do PR antes do merge e o resultado da master depois. Serviços e credenciais do CI são descartáveis.

Para reproduzir na raiz do workspace:

~~~bash
docker compose --profile testing up -d --wait postgres-test redis-test
docker compose exec -T api composer check
docker compose exec -T api composer audit --locked --no-interaction
docker compose exec -T api composer dump-autoload --optimize --strict-psr
~~~

## Checklist para qualquer funcionalidade nova

- [ ] Definir capacidades e testar cada papel aplicável, incluindo recusas sem efeitos.
- [ ] Receber Tenant Context explicitamente; validar autorização atual antes de dados/efeitos.
- [ ] Aplicar escopo de organização em busca, escrita, locks e relacionamentos; ownership não vem do cliente.
- [ ] Cobrir duas organizações com UUIDs reais e acesso cruzado de leitura/criação/alteração/cancelamento.
- [ ] Adaptar o contrato TenantIsolationContract ao módulo e manter seus testes no grupo security.
- [ ] Garantir integridade PostgreSQL, controle de versão e concorrência nas invariantes críticas.
- [ ] Reautorizar jobs na execução; particionar caches e chaves por tenant; nunca guardar contexto entre execuções.
- [ ] Usar sessão/CSRF e origem reconhecida nas rotas SPA; não introduzir autenticação alternativa sem revisão.
- [ ] Serializar somente campos públicos; manter Problem Details e não incluir trace, hash, cookie ou token.
- [ ] Não registrar payloads/credenciais; validar também logs de exceções e integrações externas.
- [ ] Preservar limites arquiteturais; adicionar regressão que reproduza cada falha corrigida.
- [ ] Aprovar análise, arquitetura, testes críticos/restantes, auditoria de dependências e CI do commit atual.

## Condições adicionais para módulos financeiros

Antes da liberação: checklist acima completo, contrato de isolamento conectado à persistência real do módulo, matriz de autorização revisada para suas operações e invariantes financeiras documentadas/testadas. Incluir precisão/arredondamento, estados de realização/cancelamento, efeitos sobre saldos, idempotência, histórico imutável e rollback/concorrência nas operações relevantes. Dados históricos precisam manter autoria e organização sem reatribuição por mass assignment.

Exigir ausência de falhas críticas/altas conhecidas sem tratamento e revisão de segurança das novas superfícies. Fixtures atuais demonstram o mecanismo de isolamento e não dispensam testes do domínio financeiro. Antes de produção, revisar HTTPS/proxy/cookies, segredos, exposição dos serviços, logs/retenção e recuperação de backups no ambiente efetivo.

## Riscos residuais e limites

| Limite | Tratamento/condição |
| --- | --- |
| Sem MFA, login social ou recuperação de senha | Fora do escopo da milestone; projetar e revisar antes de adicionar |
| Banco compartilhado sem PostgreSQL RLS | Isolamento depende de contexto explícito, SQL com escopo, autorização e constraints; revisão e contrato obrigatórios em cada módulo |
| HTTP e portas locais de desenvolvimento | Não são configuração de produção; exigir HTTPS e revisar redes/proxies e Secure no destino |
| Logs externos ou texto livre desconhecido | Não registrar segredos; processador cobre Monolog configurado, não banco/proxy ou um futuro logger que o contorne |
| Versões invalidam sessões/contextos de forma ampla | Comportamento conservador; não reduzir revalidação para evitar reautenticação |
| Auditoria de dependências pontual | CI repete consulta a cada execução; avisos novos podem bloquear uma entrega antes aprovada |
| Sem pentest externo, carga ampla ou certificação | Suíte não prova ausência de todas as vulnerabilidades; revisar antes de publicação |
| Financeiro e worker de produção ainda não entregues | Validar integrações reais, filas, retries e invariantes ao implementá-los |

Na revisão atual, os avisos encontrados foram corrigidos e não restou falha crítica/alta conhecida sem tratamento dentro do escopo examinado. Isso não equivale a certificação de segurança nem aprovação automática para produção. Os resultados verificáveis ficam nos PRs e execuções de CI da #73.
