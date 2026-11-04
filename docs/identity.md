# Identidade e usuários

## Domínio (#46)

User é uma identidade global independente de organizações. Pertence a IdentityAccess; memberships e autorização por organização permanecem nas próximas issues. A entidade é imutável, sem Eloquent, credenciais ou referências HTTP.

Atributos: id interno positivo (nulo antes de persistir), UUID público, nome obrigatório aparado de 1–255 caracteres Unicode sem controles, EmailAddress, estado active/inactive, emailVerifiedAt opcional e datas imutáveis. Versão 0 representa identidade ainda não persistida; versões positivas representam snapshots persistidos.

E-mail é ASCII de até 254 caracteres, validado sintaticamente e normalizado com trim e lowercase no endereço inteiro. Não remove pontos, aliases +tag ou aplica regras de provedores; domínios internacionais devem chegar em punycode. SMTPUTF8 não é suportado nesta etapa. A restrição de unicidade será garantida no PostgreSQL.

Criação inicia ativa e não verificada. Ativação/desativação são reversíveis e não apagam a identidade. Alterar o endereço normalizado limpa a verificação; mudar somente caixa ou nome a preserva. Não existe envio/confirmação de e-mail, exclusão física ou autenticação nesta tarefa. Datas não podem recuar; uma verificação restaurada deve estar entre criação e atualização.

## Schema e migração (#47)

A migration aditiva conserva users e seus IDs, hashes e relações existentes. Acrescenta UUID único obrigatório, status e version; normaliza e-mail e converte datas UTC para timestamptz(6). A unicidade original do e-mail, combinada à constraint que exige formato canônico, impede cadastros que difiram apenas por caixa. Constraints protegem nomes, estado, versão, datas e formato de hash bcrypt/Argon2id. A sintaxe completa do e-mail é validada no domínio.

Antes de alterar o schema, valida endereços, colisões após normalização e datas legadas. Dados incompatíveis interrompem a migration para reconciliação explícita, sem unir contas ou trocar senhas silenciosamente. PostgreSQL executa a migration transacionalmente. Down remove apenas a extensão; não apaga usuários. A normalização de e-mail é mantida no rollback e datas retornam à precisão anterior. Nenhum banco de desenvolvimento foi migrado automaticamente.

Factories usam UUID, estado ativo por padrão e estados inactive/unverified. Hashes das fixtures são exclusivos de teste. Ainda não há fluxo de verificação nem autenticação.
