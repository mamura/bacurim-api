# Contrato reutilizável de regressão multi-tenant (#76)

TenantIsolationContract é uma base de testes, não um repositório genérico de produção. O módulo fornece adaptadores prepareStorage/createResource/readResource/updateResource/archiveResource que chamam seus casos de uso e persistência reais. O valor de teste pode ser mapeado ao nome/descrição de um recurso do módulo; domínio e validações próprias continuam em testes específicos.

O contrato usa PostgreSQL exclusivo, usuários/memberships reais e exige contexto explícito. Verifica IDs conhecidos de outra organização, consulta, criação, atualização, arquivamento, troca A→B→A no mesmo adaptador e revogação após emissão do contexto. Apenas NotFound pode ser traduzido para null no adaptador de leitura; Forbidden não pode ser escondido como ausência.

TenantIsolationContractTest demonstra a conexão com ProbeUseCases/TenantExecution reais e a tabela temporária de probes. Não registra endpoints ou dados financeiros de produção. Módulos financeiros futuros devem conectar o mesmo contrato ao seu repositório/caso real e acrescentar testes de campos não editáveis, FKs compostas, operações em lote, agregações/joins, paginação e suas invariantes financeiras.

A proteção HTTP contra organization_id arbitrário, UUID externo e contexto residual é exercitada por TenantIsolationApiTest; MembershipSecurityTest e TenantResolutionTest preservam os cenários dos endpoints reais. O contrato de base não substitui CSRF, autenticação, rate limiting ou testes de serialização HTTP.
