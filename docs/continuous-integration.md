# Integração contínua

## Workflow (#33)

`.github/workflows/ci.yml` executa em pull requests para master, pushes na master e acionamento manual. Usa Ubuntu 24.04, PHP 8.3 e Composer 2, com ações fixadas por SHA. Ao atualizar uma ação, revisar origem/release e substituir o SHA intencionalmente.

As dependências vêm de composer.lock com require-dev. O cache armazena downloads do Composer por sistema, PHP e lockfile, não uma pasta vendor compartilhada. O workflow só solicita contents:read e não depende de credenciais da aplicação ou produção. Checkout não persiste credenciais.

Execuções redundantes do mesmo PR/ref são canceladas. O job tem limite de 15 minutos. O nome de verificação é Quality e deve permanecer estável para configurar proteção de branches.

A integração dos checks e do banco de testes está concluída. Este workflow não executa deploy nem publica imagens.

## Checks e serviços (#34)

O job Quality inicia PostgreSQL 18.6 exclusivo para a execução, com health check e credenciais públicas de teste. TEST_DB_HOST=127.0.0.1 adapta o endereço do serviço ao runner; o bootstrap conserva os nomes de banco/role protegidos. Drivers de cache, sessão e e-mail são em memória e a fila é síncrona. Não copia o .env local.

`composer check` executa uma vez validação estrita, Pint, PHPStan/Larastan e toda a suíte PHPUnit, incluindo integração e arquitetura. Não há execução redundante de suítes individuais no workflow. Qualquer falha retorna status não zero e impede sucesso do job; não há continue-on-error. A saída dos checks e os logs do PostgreSQL em caso de falha permitem diagnóstico sem imprimir configurações de produção.

Redis não é iniciado porque nenhuma suíte atual exige um servidor Redis: testes HTTP usam cache array e a persistência testada é PostgreSQL. Quando existir integração que exija Redis real, adicionar serviço e isolamento próprios, junto com os testes correspondentes. Traefik, Nginx e o Compose fora do repositório não são necessários ao CI.

## Proteção da master e validação (#37)

A regra clássica da master exige pull request, check Quality aprovado, branch atualizada e resolução de conversas. As regras também se aplicam a administradores; force push e exclusão da branch não são permitidos.

Não há aprovação obrigatória de outro revisor, para permitir o fluxo atual com um mantenedor. Revisão independente continua recomendada quando disponível. A proteção é efetiva no plano Free porque este repositório é público; reavaliar o plano antes de torná-lo privado. A configuração vive no GitHub, fora dos arquivos versionados, e deve ser refeita em eventual migração do repositório.

O [PR de validação #38](https://github.com/mamura/bacurim-api/pull/38) registra o exercício completo. Um teste temporário provocou [falha controlada no Quality](https://github.com/mamura/bacurim-api/actions/runs/41249173860), com saída 1, e o GitHub indicou merge bloqueado. O teste foi removido antes da integração; os checks do PR registram a execução de recuperação. O workflow instala as dependências e inicia seu próprio PostgreSQL no runner, sem depender do Compose local ou de serviços externos do workspace.
