# Organizações

A #39 implementa a empresa como raiz do módulo Organizations. Não cria endpoints públicos, usuários, memberships ou isolamento efetivo de tenant.

## Domínio (#40)

Organization é imutável e independente de Eloquent. Tem id interno positivo (nulo até persistir), UUID público canônico não sequencial, nome obrigatório de 1 a 255 caracteres Unicode, razão social opcional com o mesmo limite, documento opcional, estado e timestamps imutáveis. Nomes são aparados, preservam acentos/caixa e rejeitam controles. Razão social vazia vira null.

Criação inicia ativa. Ativação/desativação são reversíveis e idempotentes quanto ao estado; preservam identidade e criação. Atualizações não podem recuar no tempo. Não existe operação comum de exclusão física. Atualização de detalhes substitui nome, razão social e documento; null limpa opcionais.

Documento aceita CPF e CNPJ numérico ou alfanumérico, normaliza pontuação usual/espaços e caixa, valida formato e dígitos verificadores e rejeita sequências numéricas repetidas. Não consulta situação cadastral nem prova titularidade. O algoritmo alfanumérico usa ASCII menos 48 conforme o [manual da Receita Federal](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf). Documento e nome não são globalmente únicos: organizações distintas podem ter o mesmo documento.

O UUID é validado no domínio, mas a unicidade será garantida pelo banco na persistência. Ele não concede autorização. Geração de UUID e relógio pertencem aos adaptadores; o domínio recebe valores explícitos.

## Schema PostgreSQL (#41)

organizations usa bigint interno, uuid único, nomes varchar(255), documento varchar(14) opcional, estado active/inactive e timestamps com timezone e microssegundos. Constraints rejeitam nomes vazios/controles, documento fora do formato canônico, estado inválido e datas inconsistentes. Dígitos verificadores são responsabilidade do domínio; SQL valida a estrutura. Version inteiro positivo prepara o controle de concorrência na persistência. Não há índice especulativo de estado ou unicidade de documento/nome. Factory oferece organizações ativas/inativas. Rollback é validado somente no banco exclusivo de testes; nenhum banco de desenvolvimento foi migrado automaticamente.
