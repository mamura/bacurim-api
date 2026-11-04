# Contexto fora do HTTP

TenantJobIdentity transporta somente UUID público da organização e do ator. fromContext deriva esses identificadores do contexto autorizado no despacho. O DTO não contém sessão, ORM, senha, papel, conexão, ID interno nem uma decisão de acesso duradoura. Payloads de fila são produzidos pelo servidor; nunca aceitar metadados arbitrários do cliente como identidade autenticada.

TenantJobRunner reconstrói o contexto na execução com os estados atuais e exige uma TenantCapability definida pelo código da operação. Usa organização bloqueada e usuários bloqueados em ordem estável, reconsulta identidade/membership e reaproveita ResolveTenantContext, TenantAuthorization e MembershipPolicy. Usuário/organização inativos, organização inexistente, vínculo revogado ou capacidade removida impedem a operação antes dos efeitos. Não utiliza MembershipActor, Request ou Session.

Jobs devem chamar runner.run(identity, capacidade fixa, callback que recebe TenantContext), e repassar esse argumento aos casos/repositórios. Não serializar o próprio TenantContext como autorização. Uma mudança de papel entre despacho e execução é avaliada pela permissão atual. Não armazenar contexto no objeto de job, serviço estático, singleton ou sessão do worker; runner não guarda nenhum contexto e pode ser reutilizado após falhas.

O callback roda em transação com retry de deadlocks. Deve conter trabalho transacional de banco; efeitos externos irreversíveis exigirão idempotência e estratégia própria de pós-commit/outbox quando forem implementados. Não capturar contexto para executar depois de retornar do callback. Fila/locks/idempotência e cache de dados empresariais devem incluir a organização em suas chaves. O prefixo global de aplicação não oferece isolamento entre organizações.

Listeners e comandos agendados seguem o mesmo contrato por organização, ator e capacidade explícitos, revalidando a cada execução. Não inferir organização do primeiro registro nem reutilizar o contexto anterior. Não há ator nulo ou administrador global implícito: operações técnicas sem usuário exigirão identidade e política específicas futuras.

TenantJobIsolationTest usa jobs ShouldQueue representativos serializados e executados no dispatcher síncrono, além de reutilizar o mesmo runner entre organizações e após rollback. Demonstra ausência de dependência HTTP e isolamento sem introduzir funcionalidades financeiras ou configurar um worker de produção nesta milestone. O backend de fila e o agendamento operacional permanecem configurações da aplicação.
