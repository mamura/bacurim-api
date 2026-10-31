# ADR-0001 — Monólito modular e contextos iniciais

Status: adotado na fundação (#13–#18).

## Contexto

O produto distingue realidade econômica, compromissos e movimentações, exige histórico confiável e inclui importação, análise de resultados e projeção conhecida de 12 meses. Os documentos Product Definition 08–11 e Planejamento estão sintetizados no [mapa dos contextos](../context-map.md).

## Decisão

Manter um único deploy Laravel organizado em seis módulos lógicos: IdentityAccess, Organizations, Financial, DataImport, Reporting e Forecasting. Contas, eventos, compromissos, realizações, transferências, recorrências e correções ficam em Financial para preservar invariantes coordenadas sem criar dependências recíprocas entre contas e transações.

O mapa permite IdentityAccess → Organizations e DataImport/Reporting/Forecasting → Financial por contratos públicos. Importação não grava diretamente no banco financeiro; Reporting e Forecasting não detêm os fatos que consultam. Onboarding e dashboards são jornadas de composição, sem módulo próprio nesta etapa.

## Consequências

A evolução é incremental, sem microsserviços, broker ou pacote de descoberta de módulos obrigatório. Um módulo não é um agregado único e não implica tabela por conceito. Financial exige disciplina interna para não se tornar um agrupamento genérico; revisar seus limites quando fluxos concretos demonstrarem necessidade de separação, preservando invariantes e ausência de ciclos.

Alternativas de separar contas/transações imediatamente ou criar um módulo por tela/milestone foram rejeitadas por acoplamento e fragmentação antecipados. A sequência de entrega não determina fronteiras do domínio.
