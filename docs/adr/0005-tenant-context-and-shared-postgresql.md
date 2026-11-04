# ADR-0005 — Contexto explícito por organização e PostgreSQL compartilhado

Status: adotado na #66, implementação incremental nas #67–#72. Complementa o ADR-0004 e substitui as alternativas ainda abertas de tenancy nos ADRs 0003/0004 e no guia histórico de Shared Kernel.

## Decisão

Uma organização constitui a fronteira de tenant no MVP. Usa-se organizations.id como FK organization_id em tabelas compartilhadas PostgreSQL e organizations.public_id como UUID público. Não criar tabela Tenant nem um segundo identificador redundante de tenant/empresa. IdentityAccess é dono da resolução/autorização; Organizations fornece apenas seu contrato público.

TenantContext é um valor imutável de SharedKernel/Application com organizationId interno, organizationPublicId, actorPublicId, actorVersion, membershipPublicId e membershipVersion. Os UUIDs são canônicos e as referências/versões positivas. Não contém Request, Session, ORM, credenciais ou papel global. Construí-lo só comprova estrutura: a autorização deve revalidar usuário, organização, vínculo e permissão atuais antes da operação.

A organização vem exclusivamente de {organization} na rota /api/v1/organizations/{organization}/..., nunca de header, query, corpo, sessão, primeiro vínculo ou tenant padrão. A borda autentica e valida sessão antes de resolver organização ativa e membership. Contexto ausente/contraditório interrompe operações; erros seguem Problem Details. UUID conhecido não concede acesso.

Globais: health/up, CSRF, login/logout/me, organizações do próprio usuário. Não selecionam tenant. Dependentes de tenant: gestão de membros e futuros recursos financeiros sob a organização explícita. Cadastro inicial de organização continua fluxo específico atômico, sem selecionar organização existente.

A borda entrega TenantContext como argumento obrigatório aos casos e às portas de persistência. Contexto pode existir apenas como atributo temporário da requisição, removido em finally, sem singleton mutável, sessão ou container com tenant corrente. Políticas centralizadas continuam sendo fonte dos papéis e permissões. O middleware faz validação inicial; Application revalida antes de operar.

## Persistência e processos externos

Tabelas pertencentes à organização exigem FK não nula, índices e referências compostas quando necessário. Consultas/escritas devem incluir organization_id explicitamente e não aceitar sua troca. Global Scopes não bastam e RLS não é requisito. Fixtures representativas provarão os contratos sem criar funcionalidades financeiras fora do escopo.

Jobs recebem identificadores mínimos de organização/ator, reconstroem contexto e revalidam acesso na execução; não serializam sessão, ORM ou uma autorização duradoura. Não há bypass com ator nulo. Contexto não é mantido entre execuções. Identidade técnica futura requer política própria.

## Consequências

As permissões podem ser diferentes para o mesmo usuário em organizações distintas. Snapshot de contexto não autoriza indefinidamente e versões permitem rejeitar contexto obsoleto. O núcleo compartilhado permanece independente dos módulos/framework. Isolamento deve ser preservado por adaptadores, casos e integridade do banco, com testes reais entre organizações.
