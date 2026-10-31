# Contribuindo com a API

## Trabalho por issue

1. Confirmar escopo, critérios de aceite e dependências da issue.
2. Atualizar master e criar uma branch curta: `feat/<issue>-<descricao>`, `fix/<issue>-<descricao>`, `docs/<issue>-<descricao>`, `chore/<issue>-<descricao>` ou `test/<issue>-<descricao>`.
3. Implementar uma responsabilidade por vez, com testes adequados ao comportamento e documentação do que mudou.
4. Executar `composer check` no Docker com o banco exclusivo de testes iniciado, revisar o diff e criar commits.
5. Enviar a branch e abrir pull request para master com o template. Relacionar a issue com `Closes #N` quando o PR completar seus critérios.
6. Revisar o resultado e aguardar Quality aprovado no commit atual. Integrar pelo GitHub respeitando as regras efetivamente configuradas; não usar bypass de falhas.
7. Confirmar a execução pós-merge e o encerramento da issue. Uma tarefa não está finalizada somente porque os arquivos locais foram alterados.

No workspace, execute Git dentro de bacurim-api. Docker Compose/Dockerfiles ficam na raiz e não devem ser adicionados à API. Mudanças na infraestrutura externa precisam ser identificadas no PR e acompanhadas de instruções reproduzíveis; não presumir que um clone isolado contém esses arquivos.

## Commits

Use `tipo: descrição objetiva` em inglês. Tipos usuais: feat, fix, test, docs, refactor, chore, ci. Descreva o resultado, não a conversa. Um corpo pode explicar motivação/validação; não inclua credenciais. O número da issue vai na descrição do PR ou no corpo do commit. Breaking changes precisam de discussão de compatibilidade e contrato antes da integração.

## Revisão e integração

O PR deve explicar o problema, o comportamento final, a validação realizada e riscos relevantes. Revisor verifica arquitetura, contrato HTTP, isolamento, histórico/precisão financeira quando aplicável e ausência de segredos. Exemplos de classes ou rotas só de testes não se tornam APIs públicas por conveniência.

Priorizar revisão independente quando disponível. Em manutenção individual, revisar o próprio diff e os resultados de CI continua obrigatório; isso não equivale a uma aprovação formal de outro usuário. Quantidade de aprovações e forma de merge seguem as regras disponíveis/configuradas no GitHub, documentadas no guia de CI.

## Definition of Done

- Critérios da issue atendidos, sem marcar capacidades futuras como implementadas.
- Testes de comportamento e regressão apropriados; suítes existentes preservadas.
- Composer check aprovado, sem esconder falhas por baseline/ignore injustificado.
- Documentação, contratos e ADRs atualizados quando necessário.
- Nenhum segredo, alteração incidental ou dado de desenvolvimento no commit.
- Commit e branch publicados; PR relacionado à issue e Quality aprovado no HEAD atual.
- Revisão concluída, integração confirmada e issue encerrada após publicação na master.
- Limitações reais registradas com evidência, incluindo restrições de permissões/recursos do GitHub quando existirem.

O pipeline automatiza checks, não substitui revisão de produto, arquitetura ou segurança. A Definition of Done não exige criar testes irrelevantes para edições puramente documentais.

Consulte [testes e qualidade](docs/testing-and-quality.md) e [integração contínua](docs/continuous-integration.md).
