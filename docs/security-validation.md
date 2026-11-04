# Validação de segurança de identidade e tenancy (#73)

A validação usa sessão real, PostgreSQL exclusivo e Redis exclusivo de testes, além das políticas e casos implementados. Não substitui auditoria externa ou revisão antes de produção. Cada etapa registra cenários novos e reaproveita a cobertura existente.

## Autenticação e sessões (#74)

A cobertura existente inclui credenciais corretas/incorretas, usuário inexistente/inativo com erros equivalentes, fixação/regeneração de ID e CSRF, logout/replay, expiração absoluta/backend, CORS exato, cookies Secure/HttpOnly/Lax e limites por email+IP/IP com proxy confiável explícito.

AuthenticationSecurityTest acrescenta adulteração de cookie sem invalidar o cookie legítimo, CSRF anterior ao login recusado sem desconectar sessão válida, stamps de segurança ausentes/malformados/futuros, expiração real Redis seguida de reautenticação e sigilo de falhas esperadas em respostas/logs capturados. O teste Redis verifica o DB 15 antes de qualquer operação e remove somente as chaves criadas.

O teste de logging desta etapa cobre o comportamento normal de autenticação; mensagens inesperadas e contextos arbitrários serão auditados na #78. Nenhuma credencial de desenvolvimento é usada nos testes.
