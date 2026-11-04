# Contexto de organização ativa

A estratégia está no [ADR-0005](adr/0005-tenant-context-and-shared-postgresql.md). TenantContext é explícito, imutável e contém referências/versões da organização, ator e membership. O ID interno não é serializado pela API.

## HTTP

Ordem obrigatória: api (Sanctum stateful/CSRF), auth:sanctum, auth.session.valid, tenant.context. O middleware ResolveTenant lê exclusivamente o parâmetro organization, busca OrganizationDirectory, exige organização ativa, usuário ativo com versão de sessão atual e vínculo ativo. A política existente concede entrada ao contexto para todos os quatro papéis com leitura financeira; a operação específica ainda exige sua permissão, por exemplo gestão de membros para Owner/Admin.

Gestão de membros agora recebe esse middleware. Login/logout/me, organizações do próprio usuário, saúde e CSRF são globais e não resolvem tenant. Não há organização padrão nem seletor persistido em sessão. Header, corpo e query não escolhem a organização.

O atributo de Request tem chave TenantContext::class e existe somente durante a execução interna do middleware; finally o remove inclusive em falhas. TenantContextReader é uma conveniência da borda para obter o argumento tipado; não é usado por Domain/Application. Não registrar contexto como singleton nem guardar Request em serviços persistentes.

Erros RFC 9457: 401 identidade/sessão inválida; 404 UUID inválido ou organização inexistente; 403 organização inativa, membership ausente/revogado ou permissão negada; 422 configuração de rota sem parâmetro organization. Um identificador conhecido continua sujeito a autorização. Após entrar no contexto, recursos de outro tenant devem resultar em 404, sem detalhes do recurso externo.
