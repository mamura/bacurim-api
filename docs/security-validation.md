# Validação de segurança de identidade e tenancy (#73)

A validação usa sessão real, PostgreSQL exclusivo e Redis exclusivo de testes, além das políticas e casos implementados. Não substitui auditoria externa ou revisão antes de produção. Cada etapa registra cenários novos e reaproveita a cobertura existente.

## Autenticação e sessões (#74)

A cobertura existente inclui credenciais corretas/incorretas, usuário inexistente/inativo com erros equivalentes, fixação/regeneração de ID e CSRF, logout/replay, expiração absoluta/backend, CORS exato, cookies Secure/HttpOnly/Lax e limites por email+IP/IP com proxy confiável explícito.

AuthenticationSecurityTest acrescenta adulteração de cookie sem invalidar o cookie legítimo, CSRF anterior ao login recusado sem desconectar sessão válida, stamps de segurança ausentes/malformados/futuros, expiração real Redis seguida de reautenticação e sigilo de falhas esperadas em respostas/logs capturados. O teste Redis verifica o DB 15 antes de qualquer operação e remove somente as chaves criadas.

O teste de logging desta etapa cobre o comportamento normal de autenticação; mensagens inesperadas e contextos arbitrários serão auditados na #78. Nenhuma credencial de desenvolvimento é usada nos testes.

## Papéis e efeitos negados (#75)

RoleSecurityTest usa casos de uso e PostgreSQL para validar os quatro papéis atribuíveis pelo Owner, impedir qualquer alteração de Owner por Admin e bloquear autoelevação de Member/Viewer. Negações são acompanhadas da comparação completa das linhas de membership, comprovando que estado, papel e versão permanecem iguais.

Também valida último Owner sem mutação parcial, contexto antigo recusado após reativação no mesmo papel e rebaixamento permitido de Owner para Admin somente com outro Owner ativo, exigindo um contexto novo. A matriz unitária/API e o vínculo específico por organização permanecem nas suítes existentes.

## Concorrência e ciclo de contexto (#77)

MembershipConcurrencyTest executa dois processos/conexões reais com barreira que confirma ambos aguardando o lock da organização em pg_stat_activity. Além das remoções simultâneas de si próprios, testa dois Owners tentando revogar/rebaixar um ao outro: só uma operação conclui e o ator que perdeu o vínculo/papel é recusado após adquirir o lock. Duas inclusões simultâneas do mesmo usuário produzem um vínculo e um conflito, sem duplicação.

O teste de jobs acrescenta acesso a UUID de recurso de outra organização seguido de uma execução legítima no mesmo runner. Casos existentes cobrem organizações inativas, revogação/perda de papel após despacho, rollback, sessão ainda válida em outra organização e limpeza em finally. Não há estado de tenant armazenado no worker. Esses testes não representam teste de carga nem avaliam todos os cenários de produção.
