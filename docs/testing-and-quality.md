# Estratégia de testes e qualidade

## Ambiente PHPUnit (#26)

PHPUnit 11 está fixado pelo composer.lock e é executado com `composer test`, dentro do container `api`. `phpunit.xml` é a fonte das variáveis de teste; não é necessário copiar um `.env.testing`. Os valores críticos usam `force=true` para não herdar APP_ENV, drivers, URL de banco, chave ou debug do processo de desenvolvimento.

O bootstrap `tests/bootstrap.php` exige APP_ENV=testing e impede carregar um cache específico de testes. Os caminhos de cache de configuração e rotas são separados dos caches de desenvolvimento. Sessão e cache usam array, e-mail array, filas sync e logs null. Testes que verificam logging usam spies. Não iniciar workers, enviar e-mails ou chamar serviços reais por efeito colateral de testes.

`composer test` chama PHPUnit diretamente e não executa `artisan config:clear` sobre o ambiente de desenvolvimento. A chave fixa é exclusiva dos testes, sem segredo real. Dados de conexão de persistência serão estabelecidos na #28; testes atuais não usam banco.

Na raiz do workspace:

```bash
docker compose exec api composer test
docker compose exec api composer test -- --filter=ApiHealthTest
docker compose exec api composer test -- --testsuite=Unit
```

Os argumentos são encaminhados ao PHPUnit; falhas retornam código diferente de zero. Para um arquivo específico, use `docker compose exec api composer test -- tests/Feature/ApiHealthTest.php`. Não usar cache de configuração da aplicação em testes.
