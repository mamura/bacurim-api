# Perfis globais e migração (#115)

User é identidade global. Seu perfil tipado é standard ou consultant; novos cadastros comuns são standard. Papel owner/admin/member/viewer continua exclusivo do membership. Perfil não concede autorização empresarial.

## Migração e diagnóstico (#116/#117)

A migration 2026_11_06_000001 é aditiva, transacional no PostgreSQL, inclui default standard, NOT NULL e CHECK de valores. Não reescreve identidades, hashes, UUIDs ou histórico. Enquanto avalia os vínculos, bloqueia escritas nas tabelas envolvidas.

Antes do deploy, em janela sem escritores (API, jobs e comandos antigos), executar:

    php artisan identity:assess-memberships

O comando somente lê dados, apresenta contagens zero/um/múltiplos e UUIDs que exigem reconciliação; não imprime e-mails nem credenciais. Código 1 indica incompatibilidade. Conta status active inclusive em organizações inativas ou para usuários inativos; histórico revoked não conta.

A migration aborta antes da alteração de schema se encontrar identidade legada com mais de um vínculo active. Não promove automaticamente para consultant nem revoga vínculos. Não reiniciar a aplicação nova contra schema antigo.

Reconciliação requer decisão humana explícita: corrigir vínculos indevidos pelos mecanismos autorizados, preservando o último Owner de cada empresa e o histórico; ou adiar o rollout até haver mecanismo confiável aprovado para classificação de consultores. Não duplicar usuários nem inferir profissão pela contagem. A habilitação de consultant permanece decisão pendente e não foi criada nesta entrega.

Depois de reconciliar, repetir diagnóstico e aplicar migrations. Parar todos os escritores antigos durante a transição; versões antigas podem ignorar as novas invariantes. Executar backup e verificar o ambiente de destino antes de alterações.

## Rollback

O down remove a coluna profile, sem apagar users/memberships, mas perde a classificação. Fazer backup da classificação antes do rollback. Uma vez que consultores tenham múltiplos vínculos, o up não poderá inferir sua classificação e bloqueará sem reconciliação. Rollback do código também remove as proteções novas: não reabrir escritores antigos sem revisão. Down não é restauração de backup.

## Credenciais e identidade

Atualizações de cadastro, estado e senha preservam profile. UserDetails não aceita perfil da plataforma. Os repositórios devolvem entidades tipadas com o perfil; o contrato HTTP será evoluído de forma aditiva, sem substituir endpoints.

## Cardinalidade e locks (#118)

Incluir, reativar e registrar organização com primeiro Owner usam MembershipCardinality. A organização existente é bloqueada primeiro; os usuários envolvidos são bloqueados por ID interno crescente pelo protocolo já existente. A verificação relê a identidade após o lock e consulta vínculos active de todas as organizações, sem filtrar estado da organização ou do usuário. Standard com outro vínculo ativo recebe 409 memberships.active_limit.

O registro de organização bloqueia o ator antes da criação e verifica a cardinalidade antes de qualquer insert. A ausência de organização prévia dispensa lock de organização existente. Todos os locks são mantidos até commit/rollback. Não adquirir lock de uma organização existente depois de bloquear usuário em um fluxo de alteração global de perfil.

Vínculos revoked não contam; reativação do próprio vínculo ativo não o conta duas vezes. Consultant continua sujeito à autorização empresarial, aos papéis e ao contexto atuais. As portas de persistência são internas; SQL ou modelos usados diretamente fora dos casos de uso não são um fluxo suportado e precisam de revisão.

Regressões usam processos independentes aguardando o mesmo lock de usuário no PostgreSQL, incluindo inclusão/inclusão, inclusão/reativação, duas reativações e registro/inclusão. Casos de falha verificam ausência de organização órfã.

## Alteração privilegiada e rebaixamento (#119)

ChangeUserProfile é um caso interno com porta UserProfileChangeAuthorizer. A composição de produção usa DenyUserProfileChanges: nenhuma sessão, papel Owner/Admin ou endpoint pode habilitar Consultant. O mecanismo de aprovação continua pendente. Testes usam autorização positiva explicitamente substituída apenas no ambiente testing; isso não cria um canal administrativo de produto.

A porta UserProfiles separa a escrita privilegiada de UserRepository::update. Atualização cadastral comum com perfil divergente é recusada; fill de UserRecord rejeita mass assignment do atributo. As entradas login/adicionar/alterar membro rejeitam profile informado pelo cliente.

Uma futura integração confiável deverá autorizar antes do caso de uso. A mudança bloqueia a identidade, relê versão e vínculos active e impede Standard com mais de um vínculo. Não obtém lock de organização depois do usuário. Escreve apenas perfil/data/versão, preservando credenciais e memberships. Versão incrementada invalida sessões/contextos anteriores conforme o comportamento existente.

Falhas: 403 identity.profile_forbidden; 409 identity.profile_memberships_conflict; 409 identity.concurrent_update para versão obsoleta. Não há endpoint novo de mudança de perfil. Rebaixamento sem autorização não é disponibilizado, mesmo quando a cardinalidade permitir.
