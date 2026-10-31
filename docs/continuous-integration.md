# Integração contínua

## Workflow (#33)

`.github/workflows/ci.yml` executa em pull requests para master, pushes na master e acionamento manual. Usa Ubuntu 24.04, PHP 8.3 e Composer 2, com ações fixadas por SHA. Ao atualizar uma ação, revisar origem/release e substituir o SHA intencionalmente.

As dependências vêm de composer.lock com require-dev. O cache armazena downloads do Composer por sistema, PHP e lockfile, não uma pasta vendor compartilhada. O workflow só solicita contents:read e não depende de credenciais da aplicação ou produção. Checkout não persiste credenciais.

Execuções redundantes do mesmo PR/ref são canceladas. O job tem limite de 15 minutos. O nome de verificação é Quality e deve permanecer estável para configurar proteção de branches.

A integração dos checks e do banco de testes será concluída na #34. Este workflow não executa deploy nem publica imagens.
