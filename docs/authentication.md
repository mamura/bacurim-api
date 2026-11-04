# Autenticação por sessão

## Fundação (#53)

Sanctum 4 é usado somente no modo SPA stateful com guard web e modelo UserRecord de IdentityAccess. Não são emitidos tokens pessoais, nem criada tabela de tokens. Bearer tokens não são aceitos.

IdentityUserProvider reutiliza o guard de sessão Laravel, normaliza e-mail pelo domínio, verifica Argon2id/bcrypt pelo PasswordHasher e rejeita usuários inativos. Rehash de hashes legados usa o adaptador transacional/versão, sem impor a política de novo cadastro à senha já existente.

Sessões são criptografadas antes do armazenamento Redis e usam cookie HttpOnly/SameSite=Lax, duração de 120 minutos de inatividade e expiração ao fechar o navegador. Secure é obrigatório fora de local/testing; HTTP é uma exceção de desenvolvimento. Não há remember-me. Uma sessão só funciona entre origens stateful explicitamente autorizadas. Os endpoints serão implementados nas sub-issues seguintes.

O Redis de sessões usa conexão sessions (DB 2) e cache store dedicado; cache de aplicação permanece separado. Os testes usam redis-test descartável, DB 15 e chaves próprias; nunca fazem flush no Redis de desenvolvimento. O CI inicia seu próprio Redis. A expiração é exercitada pelo backend real com TTL e expiração explícita de uma chave de teste.

Referência: [Sanctum SPA authentication](https://laravel.com/docs/12.x/sanctum#spa-authentication).

## CSRF, CORS e Next.js (#54)

Em desenvolvimento, frontend http://bacurim.mamura.test e API http://api-bacurim.mamura.test são origens distintas no mesmo site. CORS_ALLOWED_ORIGINS exige origens exatas (esquema, host, porta); SANCTUM_STATEFUL_DOMAINS usa host[:porta], sem esquema. Não usar wildcard. Fora de local, as duas listas ficam vazias por padrão: configurar explicitamente o domínio real de produção com HTTPS.

Cookie de sessão é HttpOnly. O cookie XSRF-TOKEN é legível por JavaScript por exigência do protocolo CSRF e não contém a credencial de sessão. SESSION_DOMAIN=.mamura.test permite ler esse cookie no frontend local: somente hosts locais confiáveis devem compartilhar esse domínio. Em produção usar o menor domínio comum controlado, por exemplo .bacurim.example para app.bacurim.example e api.bacurim.example; Secure é obrigatório fora de local/testing. Não usar domínios de terceiros ou cruzar sites independentes.

Fluxo browser: GET /sanctum/csrf-cookie com credentials:include, depois requisições mutáveis com credentials:include, Accept:application/json e X-XSRF-TOKEN contendo decodeURIComponent do cookie XSRF-TOKEN. Axios deve habilitar withCredentials e withXSRFToken; fetch precisa montar o header explicitamente. O endpoint CSRF retorna 204 sem corpo. Falta/token inválido retorna 419 Problem Details. Não há exceção CSRF para login/logout.

Origin é validada exatamente na fronteira de autenticação, além de CORS e Sanctum. Referer é aceito quando Origin está ausente e sua origem é autorizada; não aceitar Origin:null ou URL com credenciais. Pedidos não autenticados de /auth/me sem origem continuam recebendo 401 quando o endpoint existir. O bootstrap CSRF rejeita origem não autorizada sem criar sessão.

Os testes não desativam o middleware CSRF: ValidateSpaCsrf mantém a verificação inclusive no PHPUnit. O browser de teste reapresenta cookies reais e utiliza sessão de banco exclusivamente de teste; o teste Redis cobre armazenamento/TTL reais. A integração Next.js de interface/BFF ainda não é implementada por esta API. Chamadas server-side que encaminhem cookies precisam preservar origem confiável e CSRF, sem inventar autenticação.
