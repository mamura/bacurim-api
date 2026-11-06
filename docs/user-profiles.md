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
