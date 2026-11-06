# Autenticação por sessão

## Fundação (#53)

Sanctum 4 é usado somente no modo SPA stateful com guard web e modelo UserRecord de IdentityAccess. Não são emitidos tokens pessoais, nem criada tabela de tokens. Bearer tokens não são aceitos.

IdentityUserProvider reutiliza o guard de sessão Laravel, normaliza e-mail pelo domínio, verifica Argon2id/bcrypt pelo PasswordHasher e rejeita usuários inativos. Rehash de hashes legados usa o adaptador transacional/versão, sem impor a política de novo cadastro à senha já existente.

Sessões são criptografadas antes do armazenamento Redis e usam cookie HttpOnly/SameSite=Lax, duração de 120 minutos de inatividade e expiração ao fechar o navegador. Secure é obrigatório fora de local/testing; HTTP é uma exceção de desenvolvimento. Não há remember-me. Uma sessão só funciona entre origens stateful explicitamente autorizadas. Os endpoints de login/logout/me estão implementados conforme as seções seguintes.

O Redis de sessões usa conexão sessions (DB 2) e cache store dedicado; cache de aplicação permanece separado. Os testes usam redis-test descartável, DB 15 e chaves próprias; nunca fazem flush no Redis de desenvolvimento. O CI inicia seu próprio Redis. A expiração é exercitada pelo backend real com TTL e expiração explícita de uma chave de teste.

Referência: [Sanctum SPA authentication](https://laravel.com/docs/12.x/sanctum#spa-authentication).

## CSRF, CORS e Next.js (#54)

Em desenvolvimento, frontend http://bacurim.mamura.test e API http://api-bacurim.mamura.test são origens distintas no mesmo site. CORS_ALLOWED_ORIGINS exige origens exatas (esquema, host, porta); SANCTUM_STATEFUL_DOMAINS usa host[:porta], sem esquema. Não usar wildcard. Fora de local, as duas listas ficam vazias por padrão: configurar explicitamente o domínio real de produção com HTTPS.

Cookie de sessão é HttpOnly. O cookie XSRF-TOKEN é legível por JavaScript por exigência do protocolo CSRF e não contém a credencial de sessão. SESSION_DOMAIN=.mamura.test permite ler esse cookie no frontend local: somente hosts locais confiáveis devem compartilhar esse domínio. Em produção usar o menor domínio comum controlado, por exemplo .bacurim.example para app.bacurim.example e api.bacurim.example; Secure é obrigatório fora de local/testing. Não usar domínios de terceiros ou cruzar sites independentes.

Fluxo browser: GET /sanctum/csrf-cookie com credentials:include, depois requisições mutáveis com credentials:include, Accept:application/json e X-XSRF-TOKEN contendo decodeURIComponent do cookie XSRF-TOKEN. Axios deve habilitar withCredentials e withXSRFToken; fetch precisa montar o header explicitamente. O endpoint CSRF retorna 204 sem corpo. Falta/token inválido retorna 419 Problem Details. Não há exceção CSRF para login/logout.

Origin é validada exatamente na fronteira de autenticação, além de CORS e Sanctum. Referer é aceito quando Origin está ausente e sua origem é autorizada; não aceitar Origin:null ou URL com credenciais. Pedidos não autenticados de /auth/me sem origem continuam recebendo 401. O bootstrap CSRF rejeita origem não autorizada sem criar sessão.

Os testes não desativam o middleware CSRF: ValidateSpaCsrf mantém a verificação inclusive no PHPUnit. O browser de teste reapresenta cookies reais e utiliza sessão de banco exclusivamente de teste; o teste Redis cobre armazenamento/TTL reais. A integração Next.js de interface/BFF ainda não é implementada por esta API. Chamadas server-side que encaminhem cookies precisam preservar origem confiável e CSRF, sem inventar autenticação.

## Login e logout (#55)

POST /api/v1/auth/login recebe JSON com email e password; campos obrigatórios string, e-mail até 254 e senha até 128 caracteres. Não aplica o mínimo novo a senhas legadas nem remove espaços da senha. Retorna 200 com data contendo public_id, name, email, status e email_verified_at. Não inclui ID interno, versão, senha, hash ou identificador de sessão no corpo.

Credenciais incorretas, usuário inexistente e usuário inativo recebem o mesmo 401 Problem Details identity.invalid_credentials. Entrada inválida recebe 422; CSRF inválido 419; origem não autorizada 403. Autenticação usa o guard web do Laravel. O login destrói o ID de sessão anterior, regenera ID/token CSRF e não usa remember-me.

POST /api/v1/auth/logout exige auth:sanctum, origem confiável e CSRF válido. Retorna 204, remove a autenticação e invalida a sessão, gerando novo ID/token CSRF para a sessão anônima. O frontend deve usar o XSRF-TOKEN atualizado devolvido nos cookies após login/logout. Um cookie de sessão é a credencial de transporte esperada, nunca um token retornado no JSON.

Eventos nativos de autenticação do Laravel podem conter dados sensíveis; não registrar payloads de Attempting/Failed, inputs de login, cookies ou headers CSRF. Não há listener do projeto que registre esses valores. Rate limiting e revogação de sessões existentes serão concluídos na #57.

## Usuário autenticado (#56)

GET /api/v1/auth/me exige auth:sanctum e devolve 200 com os mesmos campos públicos de login: data.public_id, name, email, status e email_verified_at. Não inclui versão/ID interno, organização ou permissões. Autenticação não concede autorização a uma organização.

Sem sessão válida, com cookie corrompido, sessão expirada no backend ou cookie autenticado anterior ao logout, retorna 401 Problem Details. Bearer tokens são ignorados e não provocam consulta a tabela de tokens. O teste faz login real e reapresenta cookies entre requisições sem actingAs.

## Políticas de segurança (#57)

Login permite 5 tentativas por minuto por e-mail normalizado+IP e 30 por minuto por IP, incluindo tentativas válidas (CSRF/origem inválidos são rejeitados antes do limiter). Limites têm chaves HMAC, não e-mails/IPs em texto puro, e usam Redis central (store auth_limits) fora dos testes. 429 segue Problem Details e preserva Retry-After; não bloqueia permanentemente uma conta. O PHPUnit usa store array isolado e relógio controlado para a janela de limites.

TRUSTED_PROXIES aceita apenas IPs/CIDRs explícitos do proxy, nunca *. Headers Forwarded-For/Proto/Port só são usados quando o peer é confiável; Host encaminhado não é aceito. No ambiente local foi configurado o IP observado do Traefik (172.18.0.2). Rever essa configuração se a rede/container for recriado; em produção preencher os endereços efetivos do proxy.

Rotas protegidas exigem auth:sanctum e auth.session.valid. A cada operação são relidos estado e versão da identidade. Inativo, versão divergente, carimbo ausente ou idade absoluta de 12 horas invalidam a sessão e retornam 401. A expiração por inatividade continua 120 minutos no backend.

A política inicial é intencionalmente ampla: qualquer alteração da identidade pelos casos de uso (perfil, estado ou senha) muda version e revoga as sessões anteriores na próxima requisição, inclusive se o usuário for desativado e reativado antes disso. Rehash no login captura a nova versão. Não há logout por perfil de dispositivo ou promise de revogação por alteração SQL que ignore os casos de uso/version.

O authorizer interno de troca de senha agora aceita apenas o próprio ator ativo de um request autenticado e validado por auth.session.valid. O contexto é removido ao sair da operação. Fora desse request, segue negado por padrão; não há novo endpoint público de senha nesta milestone.

Respostas de autenticação/CSRF usam Cache-Control:no-store,private e Pragma:no-cache, inclusive erros. Cookies continuam HttpOnly para a sessão, Lax e Secure obrigatório em produção. Não copiar cookies/tokens para logs ou armazenamento JavaScript.

## Contrato consolidado e validação (#58)

| Endpoint | Entrada/sucesso | Falhas principais |
| --- | --- | --- |
| GET /sanctum/csrf-cookie | 204 e cookies | 403 origem não confiável |
| POST /api/v1/auth/login | email/password; 200 e data do usuário | 422 entrada, 401 credenciais, 419 CSRF, 429 limite |
| POST /api/v1/auth/logout | cookies e CSRF; 204 | 401 sem sessão válida, 419 CSRF |
| GET /api/v1/auth/me | cookie; 200 e data do usuário | 401 sem sessão válida |

419 tem code csrf_token_mismatch; cliente deve inicializar CSRF novamente. Não repetir login/mutações indefinidamente após 401/419/429. Seguir Retry-After em 429 e encaminhar o usuário para login em sessão expirada/revogada. Recursos e organização ativa continuam fora do contrato desta milestone.

Testes cobrem guard real, bcrypt legado/Argon2id, espaços em senhas, NUL rejeitado em verificação, cookies e CSRF reais, fixation/logout/replay, expiração por backend e idade absoluta, estado/versão, dois dispositivos, authorizer de senha, limites por par/IP, proxies e CORS. Não usam actingAs ou bypass CSRF. Não são auditoria externa, prova de ausência de side channels ou testes de carga.

Reprodução local precisa dos serviços postgres-test e redis-test no profile testing. Redis de teste equivalente para um workspace separado:

    services:
      redis-test:
        image: redis:7-alpine
        profiles: [testing]
        command: ["redis-server", "--save", "", "--appendonly", "no"]
        tmpfs: [/data]
        healthcheck:
          test: ["CMD", "redis-cli", "ping"]
          interval: 2s
          timeout: 3s
          retries: 15

Não publicar porta nem usar volume persistente. API/testes devem estar na mesma rede; se necessário, TEST_REDIS_HOST e TEST_REDIS_PORT mudam apenas o endereço. Nunca apontar para Redis de produção. O CI já disponibiliza os dois serviços sem o Compose externo.

Na raiz do workspace:

    docker compose --profile testing up -d --wait postgres-test redis-test
    docker compose exec api composer check

No .env da API dentro do Docker, REDIS_HOST=redis, SESSION_DRIVER=redis, SESSION_CONNECTION=sessions e SESSION_DOMAIN=.mamura.test no desenvolvimento; CORS/Sanctum precisam das listas locais exatas documentadas. Usar a APP_KEY existente. Em produção configurar HTTPS, domínio comum controlado e proxies reais; listas de origem/stateful vazias impedem autenticação por padrão.

Riscos/limites: todos os hosts que recebem cookies no domínio compartilhado precisam ser confiáveis; a lista local de senhas comuns não é uma base completa de vazamentos; revogação usa a versão de identidade de forma ampla, inclusive perfil; alterações diretas no banco que ignorem version não têm essa garantia. Sessões stateful não implementam papéis, memberships ou tenant. A validação abrangente de segurança/multiprocesso continua na #73.

O cookie de sessão usa o nome dedicado bacurim_api_session para evitar colisão com outros projetos locais. A configuração local de APP_URL foi ajustada para a URL da API, preservando APP_KEY e credenciais existentes.

Na conclusão da #58, o ambiente foi confirmado como local com zero tabelas. As migrations existentes foram aplicadas, sem criação de contas ou alteração de APP_KEY/credenciais. A verificação pelo Traefik em api-bacurim.mamura.test confirmou health 200, bootstrap CSRF 204 e respostas 401 em login inválido/me anônimo, com CORS autorizado e Problem Details.

## Perfis globais (#120)

Login e /auth/me acrescentam data.profile: standard ou consultant. Isso não altera a sessão nem concede acesso empresarial. A lista de /auth/organizations continua sendo a fonte de organizações autorizadas para acesso direto (uma), seleção (múltiplas para Consultant) ou ausência de organização (zero). Consulte [perfis globais](user-profiles.md).
