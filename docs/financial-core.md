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
