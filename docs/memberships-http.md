# API de membros e organizações

Todos os endpoints exigem sessão Sanctum válida, Origin/Referer permitido e organização explícita no caminho. Obtenha /sanctum/csrf-cookie, faça login e envie o cookie da sessão; mutações exigem X-XSRF-TOKEN. Bearer tokens não concedem acesso.

| Método | Caminho | Entrada JSON | Sucesso |
| --- | --- | --- | --- |
| GET | /api/v1/organizations/{organization}/members | — | 200, data com vínculos ativos e revogados |
| POST | /api/v1/organizations/{organization}/members | user_public_id (UUID), role | 201, data do novo vínculo |
| PATCH | /api/v1/organizations/{organization}/members/{membership} | version, role **ou** version, status: active | 200, data atualizada |
| DELETE | /api/v1/organizations/{organization}/members/{membership} | version | 204, revogação lógica |
| GET | /api/v1/auth/organizations | — | 200, data com organizações efetivas do próprio usuário |

Todos os identificadores de caminho são UUIDs públicos. GET de membros é reservado a Owner/Admin, assim como as mutações. Admin não cria Owner, não promove a Owner nem modifica/revoga/reativa Owner. Member/Viewer podem consultar as próprias organizações, mas não a lista de membros.

PATCH aplica uma operação por requisição: alterar papel de vínculo ativo ou reativar vínculo revogado mantendo o papel. DELETE recebe version no corpo JSON. Após cada mutação, a versão aumenta; o cliente deve usar a nova versão. Para reativar e trocar papel, reative primeiro e envie outro PATCH com a versão retornada. Não se cria outro vínculo para um usuário já associado.

Membership Resource expõe public_id, organization_public_id, user {public_id,name}, role, status, version, created_at, updated_at, revoked_at. Nunca expõe IDs internos, email, senha, tokens ou dados de credenciais. Organizações do próprio usuário expõem public_id, name, role, membership_public_id. Respostas são privadas e não armazenáveis em cache.

Erros usam application/problem+json: 401 sem sessão válida, 403 origem não confiável ou autorização insuficiente, 404 organização/vínculo/usuário não encontrado (vínculo de outra organização também resulta em 404 após autorizar o caminho), 409 duplicata/versão antiga/transição inválida/último Owner/usuário inativo, 419 CSRF, 422 validação, 429 limite de login. Organização inativa não concede acesso. As operações consultam os estados atuais; revogar um vínculo bloqueia a próxima operação naquela organização sem desconectar o usuário de outras.

Não há seleção implícita de tenant, cadastro/convidado automático nem endpoint de bootstrap. O registro interno com primeiro Owner será integrado ao cadastro de empresa posteriormente.
