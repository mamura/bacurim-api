
## Matriz central de autorização (#62)

| Operação | Owner | Admin | Member | Viewer |
| --- | --- | --- | --- | --- |
| Consultar dados financeiros | Sim | Sim | Sim | Sim |
| Criar/alterar/cancelar dados financeiros | Sim | Sim | Sim | Não |
| Consultar e gerenciar membros | Sim | Sim, com limites | Não | Não |
| Configurações operacionais | Sim | Sim | Não | Não |
| Responsabilidade/propriedade | Sim | Não | Não | Não |

MembershipPolicy exige correspondência da organização, vínculo active, usuário ativo e organização ativa para todas as permissões. canManageTarget centraliza o limite do Admin: não pode conceder Owner nem alterar, reativar ou revogar um vínculo cujo papel seja Owner. Owner pode gerenciar os papéis; a proteção transacional do último Owner é adicional à permissão. Os casos de uso devem consultar estados atuais, sem aceitar o papel informado pelo cliente como autoridade.

A matriz financeira declara a capacidade inicial para reutilização futura; não cria endpoints nem acesso financeiro. Políticas específicas futuras poderão restringir operações adicionais. Transferência automatizada de propriedade permanece fora do escopo.
