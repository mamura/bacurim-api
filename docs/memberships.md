# Vínculos por organização

IdentityAccess possui OrganizationMembership. User continua global e Organizations mantém seu próprio domínio. O vínculo guarda referências internas positivas a usuário e organização, UUID público, papel, estado, timestamps UTC e versão otimista. Não importa entidades de outro módulo.

Estados: active e revoked. Criação e reativação exigem usuário e organização ativos. Revogação é lógica, preserva identidade, papel e histórico de criação; revoked_at corresponde à atualização que revogou o vínculo. Reativação limpa revoked_at. Apenas vínculos ativos permitem troca de papel. Timestamps não retrocedem. A entidade é imutável.

Papéis fixos: owner, admin, member, viewer. O papel pertence ao vínculo, permitindo ao mesmo usuário papéis diferentes em organizações distintas. Uma organização ou usuário inativo impede acesso efetivo mesmo que o estado armazenado do vínculo seja active; a desativação não apaga nem reativa vínculos automaticamente.

Unicidade de usuário/organização exige consulta e restrição transacional no repositório (#61); uma entidade isolada não conhece os outros vínculos. A mesma linha deve ser reativada, sem criar duplicatas históricas. Autorização de atores, proteção do último Owner e transações serão compostas nas #62/#63 antes da exposição HTTP (#64). Nenhum endpoint é adicionado na #60.

## Persistência (#61)

organization_memberships usa FKs restritivas para users/organizations, UUID único e unicidade permanente (organization_id, user_id). Índices atendem consultas por usuário/estado e organização/estado/papel. CHECKs protegem papel, estado, versão e datas. O repositório retorna domínio puro, usa lock e versão em atualizações, recusa alteração de referências e traduz duplicatas em Conflict. findActive verifica o estado armazenado; o acesso efetivo ainda exige usuário e organização ativos na autorização. Não há delete físico.

## Casos de uso e consistência (#63)

ManageOrganizationMembers oferece add/list/changeRole/activate/revoke com organização explícita. ListUserOrganizations retorna apenas vínculos efetivos do próprio ator. Desde a #66, a gestão recebe TenantContext obrigatório e revalida organização/ator/vínculo/versões/capacidade na transação. MembershipActor permanece nas operações globais e no registro inicial; seu adapter exige identidade de sessão validada. Não aceitam papéis do cliente como autoridade.

Toda gestão adquire lock na organização antes de consultar autorização/Owners, depois locks de usuários em ordem estável e atualização otimista do vínculo. Isso serializa alterações concorrentes de memberships da mesma organização. Demover/revogar Owner exige outro Owner ativo com usuário ativo. Admin não gerencia Owners, inclusive revogados; vínculos revogados não mudam papel. Ativação mantém o papel existente e exige usuário ativo. Duplicatas exigem reativação explícita. As mutações pedem expectedVersion e recusam snapshots antigos.

RegisterOrganizationWithOwner é uma operação interna para integração futura ao cadastro: exige o mesmo ator autenticado validado, cria uma organização nova e associa o próprio ator como primeiro Owner numa única transação. Não recebe organização existente nem usuário arbitrário, e não possui endpoint nesta etapa. Falha no vínculo desfaz a organização. Não existe bootstrap de Owner em organização preexistente sem autorização.

IdentityAccess integra Organizations apenas pelo contrato público OrganizationDirectory e seu DTO. O snapshot contém identificador interno exclusivamente para integração de persistência; Resources HTTP expõem apenas UUIDs. TenantContext é explícito, sem estado global mutável; consulte docs/tenant-context.md.

A proteção aqui governa alterações de memberships; alterações internas no ciclo de vida de User/Organization continuam em seus casos de uso existentes. Integrações futuras que desativarem usuários responsáveis deverão definir o fluxo de continuidade administrativa.

## Validação final (#65)

Consulte a [matriz de permissões](memberships-authorization.md) e o [contrato HTTP](memberships-http.md). Os testes exercitam todos os papéis nos endpoints de gestão, papéis distintos por organização, revogação/reativação entre sessões reais, bloqueio de referências inativas, tentativas de escalada e vínculos de outra organização, além de CSRF em todas as mutações.

MembershipConcurrencyTest inicia dois processos PHP com conexões PostgreSQL independentes e configuração de testes forçada. Uma barreira de lock confirma no pg_stat_activity que os dois workers concorrem pela mesma organização antes de liberá-los. Tanto na revogação quanto no rebaixamento simultâneo de dois Owners, apenas uma operação pode concluir; a outra retorna memberships.last_owner e um Owner ativo permanece. Os workers recusam qualquer banco/role diferente de bacurim_test. Não exige pcntl nem novos serviços.

Limites atuais: locks de usuários em ordem estável e da organização privilegiam consistência para o MVP. Listagem de membros não tem paginação nesta etapa e inclui vínculos revogados para permitir gestão/reativação. O endpoint de organizações do usuário retorna somente vínculos efetivos e não seleciona tenant ativo.
