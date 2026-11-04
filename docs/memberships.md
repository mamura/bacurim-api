# Vínculos por organização

IdentityAccess possui OrganizationMembership. User continua global e Organizations mantém seu próprio domínio. O vínculo guarda referências internas positivas a usuário e organização, UUID público, papel, estado, timestamps UTC e versão otimista. Não importa entidades de outro módulo.

Estados: active e revoked. Criação e reativação exigem usuário e organização ativos. Revogação é lógica, preserva identidade, papel e histórico de criação; revoked_at corresponde à atualização que revogou o vínculo. Reativação limpa revoked_at. Apenas vínculos ativos permitem troca de papel. Timestamps não retrocedem. A entidade é imutável.

Papéis fixos: owner, admin, member, viewer. O papel pertence ao vínculo, permitindo ao mesmo usuário papéis diferentes em organizações distintas. Uma organização ou usuário inativo impede acesso efetivo mesmo que o estado armazenado do vínculo seja active; a desativação não apaga nem reativa vínculos automaticamente.

Unicidade de usuário/organização exige consulta e restrição transacional no repositório (#61); uma entidade isolada não conhece os outros vínculos. A mesma linha deve ser reativada, sem criar duplicatas históricas. Autorização de atores, proteção do último Owner e transações serão compostas nas #62/#63 antes da exposição HTTP (#64). Nenhum endpoint é adicionado na #60.

## Persistência (#61)

organization_memberships usa FKs restritivas para users/organizations, UUID único e unicidade permanente (organization_id, user_id). Índices atendem consultas por usuário/estado e organização/estado/papel. CHECKs protegem papel, estado, versão e datas. O repositório retorna domínio puro, usa lock e versão em atualizações, recusa alteração de referências e traduz duplicatas em Conflict. findActive verifica o estado armazenado; o acesso efetivo ainda exige usuário e organização ativos na autorização. Não há delete físico.
