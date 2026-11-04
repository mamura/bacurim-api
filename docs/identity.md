# Identidade e usuários

## Domínio (#46)

User é uma identidade global independente de organizações. Pertence a IdentityAccess; memberships e autorização por organização permanecem nas próximas issues. A entidade é imutável, sem Eloquent, credenciais ou referências HTTP.

Atributos: id interno positivo (nulo antes de persistir), UUID público, nome obrigatório aparado de 1–255 caracteres Unicode sem controles, EmailAddress, estado active/inactive, emailVerifiedAt opcional e datas imutáveis. Versão 0 representa identidade ainda não persistida; versões positivas representam snapshots persistidos.

E-mail é ASCII de até 254 caracteres, validado sintaticamente e normalizado com trim e lowercase no endereço inteiro. Não remove pontos, aliases +tag ou aplica regras de provedores; domínios internacionais devem chegar em punycode. SMTPUTF8 não é suportado nesta etapa. A restrição de unicidade será garantida no PostgreSQL.

Criação inicia ativa e não verificada. Ativação/desativação são reversíveis e não apagam a identidade. Alterar o endereço normalizado limpa a verificação; mudar somente caixa ou nome a preserva. Não existe envio/confirmação de e-mail, exclusão física ou autenticação nesta tarefa. Datas não podem recuar; uma verificação restaurada deve estar entre criação e atualização.
