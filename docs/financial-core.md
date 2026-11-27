# Financial Core — API-M2

## Escopo e rastreabilidade (#127–#132)

O módulo Financial usa as quatro camadas existentes. Financeiro → IdentityAccess somente por Application/Contracts/PublicApi; nenhuma tabela/repositório privado de identidade é consumido. TenantOperation publica a execução transacional já existente: bloqueia organização/identidades, revalida contexto/capacidade e mantém a autorização estável até commit. FinancialOperation traduz apenas violações conhecidas; erros inesperados continuam internos.

| Fonte aprovada | Contrato |
| --- | --- |
| Domain Model 6 e MVP Features 5.1 | FinancialAccount representa banco, conta digital, caixa ou outras disponibilidades; não conta a pagar/receber |
| Domain Model 7–15 e BR-001–007 | EconomicEvent reconhece income/cost/expense por competência; FinancialCommitment representa receivable/payable com vencimento independente |
| BR-011–013 | Atualização somente sem realização e com compromissos consistentes; sem redistribuição automática |
| BR-039–045 | Categoria ativa para novo evento; identidade/histórico preservados, hierarquia sem ciclos; sem mesclagem |
| BR-064–070 | Sem fechamento rígido; autoria, instante e revisões rastreáveis |
| BR-071–075 e BR-082 | Todas as portas de persistência recebem TenantContext obrigatório; perfil não concede privilégios |

Decisões confirmadas pelo responsável em 27/11/2026 nesta implementação: MVP limitado a BRL, escala 2, sem arredondamento implícito; categorias padrão somente raízes Receitas, Custos e Despesas, sem subcategorias.

Money aceita decimal em string, normaliza duas casas e opera em centavos inteiros (PHP 64 bits), até 9999999999999999.99, compatível com numeric(18,2). Rejeita floats, negativos, exponencial, vírgula, moedas distintas, casas extras e overflow. Eventos/compromissos exigem valor positivo. Zero só é permitido no objeto monetário geral.

Datas civis YYYY-MM-DD são estritas, sem timezone; competência não recebe default derivado do vencimento/cadastro. Instantes de auditoria são UTC. AccountType: bank, digital, cash, other. Categoria e evento: income, cost, expense. Compromisso: receivable para income; payable para cost/expense. No núcleo inicial, estado pending, saldo pendente integral; temporal overdue/upcoming é independente e calculado contra data explícita.

EconomicRecord é agregado de evento e lista não vazia de compromissos, sem duplicar reconhecimento econômico; soma dos compromissos deve corresponder ao valor econômico. Persistência só grava pelo agregado, sem criar compromisso independente por atalho.

## Erros

Domain lança InvalidFinancialData sem HTTP; FinancialOperation traduz para 422 financial.invalid. Recurso/referência externo ou inexistente: 404 financial.not_found, sem revelar tenant alheio. Snapshot antigo: 409 financial.concurrent_update. Incompatibilidade histórica: 409 documentado pelo caso específico. Autenticação/CSRF/403 e Problem Details seguem API-M0/M1. Não incluir SQL ou valores privados em mensagens.

## Limites

API-M2 não registra recebimentos/pagamentos, saldos arbitrários, movimentações, transferências, estornos, ajustes, cancelamento com efeitos sobre realizações, recorrência ou plano de parcelamento. API-M3 deverá ampliar estados/realizações sem reescrever o histórico, revisar constraints e conectar a fonte rastreável do saldo.

Nesta fundação não há endpoints financeiros ou tabelas financeiras. As próximas entregas concretizam contas (#133), categorias (#140), agregado econômico (#147), HTTP (#154) e validação integrada (#161), preservando estes contratos. Não inventar catálogo adicional ou automação de caixa.

## Manutenção do gate de segurança

A auditoria de 27/11/2026 identificou seis avisos em Laravel/http-kernel/mailer/routing. Correções compatíveis: Laravel 12.71.0, Symfony http-kernel/mailer/routing 7.4.24 e http-foundation 7.4.25 exigido pela resolução. Nenhum aviso foi ignorado; o composer.lock fixa as versões e a auditoria permanece obrigatória no CI.

## Contas financeiras (#133–#139)

Rotas sob /api/v1/organizations/{organization}/financial-accounts: GET/POST na coleção, GET/PATCH em /{account}. Todas exigem sessão/Origin autorizada; mutações exigem CSRF. TenantOperation revalida organização, usuário, vínculo e capacidade dentro da transação. Owner/Admin/Member escrevem; Viewer lê. UUID estrangeiro é 404 após autorização do contexto.

POST JSON: name (1–255 caracteres sem controles), type (bank/digital/cash/other). Estado inicial active. Retorna 201 com Location relativa e data {public_id,name,type,status,version,created_at,updated_at}. Não expõe chave interna, organização interna ou saldo.

PATCH JSON: version obrigatório e os campos configuráveis name/type/status (active/inactive), opcionais e independentes. Estado inativo preserva linha e identidade e continua consultável. Resposta 200; versão antiga recebe 409 financial.concurrent_update. Não há DELETE; não há saldo inicial nem ajuste arbitrário de saldo.

Listagem inclui ativos e inativos por padrão. Filtros filter[status] e filter[type]; sort=name,type,status,created_at (vírgula para múltiplos e prefixo - para desc). Default created_at asc, desempate interno estável por id. page >=1, per_page 1–100 (default20); campos desconhecidos são 422. Resposta usa data/links/meta nativos Laravel, com links relativos e somente parâmetros validados.

PostgreSQL usa FK organization restritiva, UUID único, versão e timestamps com microssegundos; updates pesquisam organization_id+UUID e verificam versão sob lock. financial_revisions registra snapshots antes/depois, ator e instante na mesma transação; falha de auditoria reverte a conta inteira. Histórico não é endpoint público de edição.

BR-067/068: renomeação e estado ficam rastreáveis. BR-071–075: toda busca/escrita exige organização. API-M3 deverá derivar saldos de movimentações rastreáveis; API-M2 não calcula nem materializa saldo. Expectativa econômica ou compromisso não altera conta.

Contrato interno de posição: GetFinancialAccountPosition recebe TenantContext e UUID e devolve AccountPosition com moeda, instante de consulta, balance nullable e source. Enquanto não existe ledger, balance=null/source=unavailable; não é zero nem saldo calculado e não há endpoint público novo. API-M3 fornecerá valor rastreável derivado das movimentações. Saldo inicial permanece pendente de regra aprovada, sem lançamento fictício. Conta inativa continua consultável historicamente; elegibilidade para novas movimentações será validada na operação API-M3, sem rejeitar/restaurar silenciosamente fatos antigos.

## Categorias financeiras (#140–#146)

Rotas sob /financial-categories no mesmo prefixo organizacional: GET/POST coleção, GET/PATCH /{category}. POST /financial-categories/initialize é a operação explícita e autorizada de onboarding para instalar os padrões; não há mutação em GET nem carga implícita em migration. Pode ser usada para organizações novas e existentes. Exige a mesma capacidade de escrita, sessão e CSRF.

POST: name, nature (income/cost/expense), parent_public_id opcional/null. PATCH: version obrigatório, name/nature/status opcionais e parent_public_id opcional; null remove o pai, ausência preserva. Resposta inclui public_id/name/nature/status/parent_public_id/is_default/version e datas. Identidade é preservada na renomeação. Não há DELETE ou mesclagem.

Representação hierárquica é lista plana paginada com parent_public_id, evitando árvore parcialmente truncada ou duplicação de recursos. GET da coleção filtra active por padrão, adequado a seleções de novos lançamentos. filter[status]=inactive ou all permite interpretar histórico; consulta individual não esconde categorias inativas. filter[nature] e sort=name,nature,status,created_at seguem as convenções das contas. Para árvore completa, o consumidor deve paginar status=all e montar a hierarquia pelos UUIDs.

Pai e filho exigem mesma natureza/organização. Criação/reorganização exige pai ativo e verifica a cadeia inteira contra ciclos sob lock de organização. Inativar pai não apaga ou inativa filhos automaticamente; estado e classificação permanecem explícitos. Renomear categoria com pai agora inativo não rompe seu histórico. Mudar natureza é recusado se houver filhos ou eventos históricos; reorganizar nunca reatribui registros econômicos.

Inicialização core-v1: somente Receitas/income, Custos/cost e Despesas/expense, sem subcategorias. Chave técnica por organização garante idempotência e unicidade; personalizações de nome/estado nunca são sobrescritas, e categorias personalizadas não são removidas nem mescladas por nome. Não há catálogo adicional presumido. Repetições concorrentes usam o mesmo lock organizacional e criam exatamente três raízes.

FK composta protege pai/natureza/organização no PostgreSQL; CHECK impede auto-referência e valida estado/natureza/datas. Ciclos longos são verificados pelo caso de uso, não por SQL administrativo arbitrário. Eventos futuros terão FK composta para impedir reclassificação histórica. Revisões registram as alterações no mesmo mecanismo das contas.

Conflitos: financial.category_cycle, financial.category_nature, financial.category_inactive, financial.category_in_use, além de financial.concurrent_update. Referências inexistentes ou estrangeiras retornam financial.not_found, sem revelar dados externos.

## Persistência econômica e limites históricos (#147–#153)

economic_events e financial_commitments usam UUIDs públicos, organization_id obrigatório, numeric(18,2)/BRL, datas civis independentes e versões. Categoria/natureza/organização e evento/compromisso/organização possuem FKs compostas restritivas. Não há referência a conta no compromisso inicial: a conta será determinada na realização API-M3, sem transformar expectativa em caixa.

EconomicRecordRepository é a única porta de escrita do agregado; FinancialCommitmentRepository somente consulta. Save é transacional, registra autoria e revisões e só retorna depois de persistir evento e filhos. Constraint triggers diferidas no PostgreSQL exigem pelo menos um compromisso, soma igual ao evento e direção compatível no commit, inclusive contra escrita SQL incompleta. No bootstrap de testes com migrate:fresh, a função é recriada de modo idempotente porque o comando apaga tabelas, não funções PostgreSQL.

Criação suporta conceitualmente uma lista explícita de compromissos com valores e datas próprios, sem gerar plano de parcelas ou redistribuir valores. Atualização preserva as identidades e a estrutura existente; compromissos sem alteração não ganham revisão/versão artificial. A API de criação utilizará um compromisso inicial por operação.

O estado persistido nesta milestone é somente pending; saldo pendente é o valor esperado, e temporal overdue/upcoming é independente. API-M3 precisará estender estados/realizações e derivar saldo válido do histórico, sem editar registros realizados. Não há operações antecipadas de pagamento, recebimento, cancelamento, recorrência ou parcelamento.

locked_at é barreira técnica de edição do agregado (nula em toda criação API-M2), não operação de realização. Save recusa financial.history_locked quando a barreira estiver preenchida. A futura realização API-M3 deverá marcar a barreira na mesma transação do ledger e adicionar suas próprias invariantes; não existe endpoint para escrever ou limpar esse campo na API-M2.

Conflitos incluem financial.event_identity (natureza/autoria imutáveis), financial.commitment_structure (não substituir/remover vínculos por PATCH) e financial.history_locked. Falha SQL inesperada não encadeia QueryException e reverte evento/filhos/revisões. Categorias históricas inativas são restauradas e consultadas sem perder referência; criação/reclassificação exige categoria ativa e compatível.
