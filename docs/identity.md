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

## Credenciais (#48)

PasswordHasher é uma porta de Application. O adaptador utiliza o driver Argon2id do Laravel para novos hashes, com salt aleatório e configuração padrão de 64 MiB, 4 iterações e 1 thread. ARGON_MEMORY e ARGON_TIME podem ajustar o custo, respeitando pisos de 19 MiB e 2 iterações; testes usam esses pisos, sem reduzir abaixo deles. O PHP precisa suportar Argon2id. Referência: [OWASP Password Storage](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html).

Política inicial: 15–128 caracteres UTF-8, permite espaços e frases, sem composição obrigatória ou trim/transformação silenciosa. Rejeita controles, entrada só de espaços e uma pequena lista explícita de valores comuns. Essa lista não equivale a um serviço completo de senhas comprometidas; ampliar a proteção antes da liberação pública conforme o risco. Não há alegação de conformidade integral NIST. Referência de comprimento: [NIST](https://www.nist.gov/cybersecurity-and-privacy/how-do-i-create-good-password).

Hashes bcrypt legados podem ser verificados e sinalizados para rehash; não há login/rehash automático implementado nesta etapa. Candidatos bcrypt maiores que 72 bytes são rejeitados para impedir equivalência por truncamento. A verificação não aplica o comprimento mínimo novo a senhas legadas. Verificação limita a entrada a 512 bytes; novas senhas têm no máximo 128 code points UTF-8. Não altera Unicode ou caixa da senha.

PasswordHash não pertence à entidade User ou a DTO público. JSON/debug são redigidos e serialização PHP é proibida. encoded() é um acesso explícito exclusivo de adaptadores de persistência/hash, nunca deve ser registrado. Parâmetros de senha usam SensitiveParameter; mensagens de erro não repetem o valor. Essas proteções não tornam seguro registrar entradas HTTP indiscriminadamente. Não há login, recuperação ou envio de credenciais.

## Persistência (#49)

UserRepository cria identidades com hash explícito, consulta por UUID/e-mail tipado e atualiza perfil/estado devolvendo entidades puras. UserCredentials fornece acesso restrito ao hash por UUID+versão e substituição com versão esperada. Perfil e credenciais compartilham version: transações com lock de linha rejeitam snapshots desatualizados, inclusive entre mudança de senha e perfil. Substituir senha limpa remember_token, mas ainda não há gerenciamento/invalidação de sessões ativas (pertence à #52).

UserRecord é o modelo de Infrastructure; App Models User é um adaptador de compatibilidade para o skeleton/factories. Nenhuma rota foi criada ou autenticada. Campos sensíveis são ocultos da serialização. O mutator de e-mail usa EmailAddress; password só aceita hash reconhecido, sem hash duplo ou gravação de texto puro. Criação/perfil fora dos casos de uso não representa um fluxo de cadastro suportado.

Colisões de e-mail/UUID são falhas Conflict com códigos estáveis. Erros inesperados de SQL viram IdentityPersistenceFailure sem encadear QueryException, cujos bindings podem conter hashes. Não registrar queries/bindings de credenciais em ferramentas de observabilidade; a proteção do adaptador não cobre listeners de query adicionados externamente.
