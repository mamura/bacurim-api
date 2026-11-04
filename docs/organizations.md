# Organizações

A #39 implementa a empresa como raiz do módulo Organizations. Não cria endpoints públicos, usuários, memberships ou isolamento efetivo de tenant.

## Domínio (#40)

Organization é imutável e independente de Eloquent. Tem id interno positivo (nulo até persistir), UUID público canônico não sequencial, nome obrigatório de 1 a 255 caracteres Unicode, razão social opcional com o mesmo limite, documento opcional, estado e timestamps imutáveis. Nomes são aparados, preservam acentos/caixa e rejeitam controles. Razão social vazia vira null.

Criação inicia ativa. Ativação/desativação são reversíveis e idempotentes quanto ao estado; preservam identidade e criação. Atualizações não podem recuar no tempo. Não existe operação comum de exclusão física. Atualização de detalhes substitui nome, razão social e documento; null limpa opcionais.

Documento aceita CPF e CNPJ numérico ou alfanumérico, normaliza pontuação usual/espaços e caixa, valida formato e dígitos verificadores e rejeita sequências numéricas repetidas. Não consulta situação cadastral nem prova titularidade. O algoritmo alfanumérico usa ASCII menos 48 conforme o [manual da Receita Federal](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf). Documento e nome não são globalmente únicos: organizações distintas podem ter o mesmo documento.

O UUID é validado no domínio, mas a unicidade será garantida pelo banco na persistência. Ele não concede autorização. Geração de UUID e relógio pertencem aos adaptadores; o domínio recebe valores explícitos.

## Schema PostgreSQL (#41)

organizations usa bigint interno, uuid único, nomes varchar(255), documento varchar(14) opcional, estado active/inactive e timestamps com timezone e microssegundos. Constraints rejeitam nomes vazios/controles, documento fora do formato canônico, estado inválido e datas inconsistentes. Dígitos verificadores são responsabilidade do domínio; SQL valida a estrutura. Version inteiro positivo prepara o controle de concorrência na persistência. Não há índice especulativo de estado ou unicidade de documento/nome. Factory oferece organizações ativas/inativas. Rollback é validado somente no banco exclusivo de testes; a #58 inicializou o schema local após confirmar banco vazio.

## Persistência (#42)

OrganizationRepository define create, findByPublicId e update na Application. O adaptador Eloquent devolve entidades, preserva timestamps e UUID, e fica registrado no OrganizationsServiceProvider. Consultas inexistentes ou UUID inválido retornam null. Criação usa transação e traduz colisão de UUID em organizations.public_id_conflict. Atualização nunca faz upsert: exige registro existente e a mesma versão. Usa transação e lock de linha, incrementa version e rejeita snapshots desatualizados com organizations.concurrent_update, inclusive quando os timestamps coincidem. Não há exclusão no contrato. Falhas inesperadas de conexão/SQL continuam internas e não são mascaradas como conflito de negócio.

## Casos de uso internos (#43)

CreateOrganization recebe OrganizationDetails, gera UUID v4 e horário UTC por portas injetadas e devolve OrganizationData. GetOrganization consulta pelo UUID público; ausência e formato inválido resultam em organizations.not_found.

UpdateOrganization recebe UUID, OrganizationDetails e expectedVersion obrigatório. A entrada substitui todos os detalhes e null limpa opcionais. ChangeOrganizationStatus recebe UUID, enum de estado e expectedVersion. Ambos verificam a versão do chamador e o repositório repete a proteção dentro da transação, cobrindo a janela entre leitura e gravação. Repetir o mesmo estado é permitido, mantendo uma nova versão da operação.

DTO de saída não expõe chave interna nem modelo Eloquent; inclui UUID, detalhes, estado, datas e versão. Documento pode conter dado pessoal: DTO é interno e não deve ser registrado em logs ou exposto por futuro endpoint sem contrato/autorização. Invariantes inválidas viram ApplicationFailure Validation (organizations.invalid), ausência NotFound e colisão/concorrência Conflict, compatíveis com o tradutor HTTP central.

Essas capacidades são internas e não fazem autorização de usuário. Nenhuma rota, cadastro público, owner implícito ou membership foi criado. Futuros adaptadores devem validar acesso antes de invocar as operações; UUID conhecido não prova acesso.

## Validação e limites (#44)

Os testes unitários não inicializam Laravel e cobrem nomes/limites Unicode, identidade, datas, versão, CPF/CNPJ, transições e coordenação dos casos de uso com portas controladas. Testes de integração usam PostgreSQL real exclusivo para constraints, migrations/rollback, factories, mapeamento, ciclo completo, colisão e snapshots desatualizados.

Falhas inesperadas de SQL são provocadas com constraints temporárias dentro da transação de teste: create/update propagam QueryException, desfazem a escrita e mantêm conexão e versão consistentes. Esses detalhes não são mensagens públicas; o tradutor HTTP existente mantém falhas inesperadas como erro interno. Nenhum endpoint de organização foi criado apenas para testar essa integração.

Controle de versão é validado com snapshots concorrentes determinísticos; teste de múltiplos processos simultâneos não faz parte desta etapa. Autorização, isolamento entre organizações e concorrência de memberships pertencem às respectivas issues API-M1. Não há promessa de produção liberada ou segurança multi-tenant concluída.

A evolução para identidade global está registrada no [ADR-0004](adr/0004-organization-identity-evolution.md). Documentos externos de produto permanecem intactos.

Execute na raiz do workspace:

    docker compose --profile testing up -d --wait postgres-test redis-test
    docker compose exec api composer test -- --filter=Organizations
    docker compose exec api composer check

Migrations de desenvolvimento podem ser aplicadas conscientemente com docker compose exec api php artisan migrate. A #39 validou migrations no banco de testes; a inicialização do schema local ocorreu na #58, após confirmar banco vazio.
