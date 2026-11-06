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

## Evolução de perfis globais (#115/#121)

| Regra | Evidência automatizada |
| --- | --- |
| BR-076: padrão Standard e perfil tipado | Unit/IdentityAccess/UserProfileTest e Integration/IdentityAccess/UserProfileTest |
| BR-077: um vínculo active para Standard | MembershipCardinalityTest e ProfileMembershipConcurrencyTest |
| BR-078: Consultant com múltiplos vínculos autorizados | MembershipCardinalityTest, UserProfileContractTest e fixtures de isolamento com mesma identidade |
| BR-079/080: zero/um/múltiplas organizações, seleção no consumidor | UserProfileContractTest; lista preservada, sem endpoint de seleção |
| BR-081: ausência/revogação não permite finanças | ProfileAuthorizationRegressionTest e contratos de tenant existentes |
| BR-082: perfil não concede papel | ProfileAuthorizationRegressionTest cobre Standard/Consultant × Owner/Admin/Member/Viewer |
| BR-083: perfil não vem de payload comum nem papel empresarial | ChangeUserProfileTest; composição de produção nega mudanças por padrão |
| BR-084: rebaixamento condicionado e atômico | ChangeUserProfileTest e ProfileChangeConcurrencyTest |
| BR-073: contexto autorizado em cada operação | Suítes HTTP/SQL/jobs existentes e contrato reutilizável preservados |
| Migração e legados incompatíveis | UserProfileTest: diagnóstico read-only, zero/um/múltiplos, falha sem promoção/remoção de histórico |

Os testes multiprocesso usam barreira PostgreSQL confirmando duas conexões aguardando o mesmo lock de identidade, além dos testes anteriores de lock de organização. Cobrem inclusão/inclusão, inclusão/reativação, duas reativações, registro/inclusão, Consultant múltiplo e promoção/rebaixamento versus inclusão/reativação/revogação. Testes não dependem de sleep para ordenar o vencedor e aceitam os resultados legais de ambas as ordens.

A autorização positiva de mudança de perfil é uma substituição explícita restrita aos testes. A implementação real permanece negada até definição do mecanismo confiável. Os testes não equivalem à entrega desse mecanismo nem a mudanças de frontend.

Riscos e rollout: [perfis globais](user-profiles.md). Não inferir Consultant por quantidade histórica de vínculos. Parar escritores antigos, diagnosticar, reconciliar explicitamente e só então migrar. Alterações de perfil incrementam version e invalidam sessões/contextos antigos. As regras não protegem SQL administrativo arbitrário que contorne os casos de uso; não foi introduzido RLS ou trigger cross-table.
