# Autenticação por sessão

## Fundação (#53)

Sanctum 4 é usado somente no modo SPA stateful com guard web e modelo UserRecord de IdentityAccess. Não são emitidos tokens pessoais, nem criada tabela de tokens. Bearer tokens não são aceitos.

IdentityUserProvider reutiliza o guard de sessão Laravel, normaliza e-mail pelo domínio, verifica Argon2id/bcrypt pelo PasswordHasher e rejeita usuários inativos. Rehash de hashes legados usa o adaptador transacional/versão, sem impor a política de novo cadastro à senha já existente.

Sessões são criptografadas antes do armazenamento Redis e usam cookie HttpOnly/SameSite=Lax, duração de 120 minutos de inatividade e expiração ao fechar o navegador. Secure é obrigatório fora de local/testing; HTTP é uma exceção de desenvolvimento. Não há remember-me. Uma sessão só funciona entre origens stateful explicitamente autorizadas. Os endpoints serão implementados nas sub-issues seguintes.

O Redis de sessões usa conexão sessions (DB 2) e cache store dedicado; cache de aplicação permanece separado. Os testes usam redis-test descartável, DB 15 e chaves próprias; nunca fazem flush no Redis de desenvolvimento. O CI inicia seu próprio Redis. A expiração é exercitada pelo backend real com TTL e expiração explícita de uma chave de teste.

Referência: [Sanctum SPA authentication](https://laravel.com/docs/12.x/sanctum#spa-authentication).
