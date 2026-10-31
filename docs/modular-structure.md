# Estrutura modular

Implementação da issue [#15](https://github.com/mamura/bacurim-api/issues/15), baseada no [mapa de contextos](context-map.md) e nas [regras das camadas](architecture-layers.md).

## Diretórios e namespaces

```text
app/Modules/
├── IdentityAccess/
├── Organizations/
├── Financial/
├── DataImport/
├── Reporting/
└── Forecasting/
```

Cada módulo contém `Domain/`, `Application/`, `Infrastructure/` e `Interfaces/`. Arquivos `.gitkeep` preservam essas quatro fronteiras no Git; não contêm implementação. As subdivisões documentadas na #14 serão criadas conforme surgirem classes reais, sem gerar entidades, contratos ou adaptadores fictícios.

O autoload PSR-4 existente mapeia `App\` para `app/`. Portanto, o caminho `app/Modules/Financial/Domain/ValueObjects/<Name>.php` corresponde ao namespace `App\Modules\Financial\Domain\ValueObjects`. O nome do arquivo corresponde ao nome da classe/interface/enum. Não é necessário alterar `composer.json`, adicionar pacotes de modularização ou criar um arquivo Composer por módulo.

## Convenções

- Nomes de módulos, camadas, classes e namespaces em inglês e PascalCase, com a mesma capitalização do caminho, inclusive em Linux.
- Um tipo principal por arquivo. Use nomes que expressem a operação ou conceito do domínio; evite `Manager`, `Helper` ou `BaseRepository` sem responsabilidade específica.
- Casos de uso em `Application/UseCases`, entradas/saídas internas em `Application/Data`, contratos públicos em `Application/Contracts/PublicApi` e portas de persistência em `Application/Contracts/Persistence`.
- Entidades, objetos de valor e serviços de domínio em suas pastas dentro de Domain, conforme a #14. Não usar modelos Eloquent como entidades do domínio.
- Adaptadores Eloquent em `Infrastructure/Persistence/Eloquent`; providers em `Infrastructure/Providers`; HTTP em `Interfaces/Http`.
- Testes seguem a estrutura de testes do projeto e identificam o módulo no caminho quando houver comportamento implementado. Não introduzir testes ou classes vazias apenas para preencher diretórios.

## Integração entre módulos

O mapa da #13 permite `IdentityAccess → Organizations`, `DataImport → Financial`, `Reporting → Financial` e `Forecasting → Financial`. Consumir apenas contratos públicos de `Application/Contracts/PublicApi` do destino. Os diretórios do PHP não impõem privacidade: essa regra deve ser observada em revisão e será automatizada na #30.

Nenhum módulo contém classes ou imports neste estágio; não existem dependências entre implementações internas. Os providers e bindings serão definidos na #16, e o exemplo técnico para exercitar as camadas na #18. O autoload permite carregar uma classe, mas isso não registra automaticamente rotas, eventos ou bindings.

## Adicionar um módulo

1. Justifique o novo contexto no mapa, indicando responsabilidade, conceitos próprios e integrações, sem ciclos.
2. Crie `app/Modules/<Module>` com as quatro camadas, usando `App\Modules\<Module>` como base de namespace.
3. Adicione apenas classes e subpastas necessárias ao comportamento planejado.
4. Registre providers quando houver bindings ou adaptadores a carregar, seguindo a #16.
5. Valide o autoload e execute os testes do módulo e os checks do projeto. A inclusão não deve exigir alterações no núcleo dos módulos existentes.

## Validação

Na raiz do workspace:

```bash
docker compose exec api composer dump-autoload --optimize --strict-psr
docker compose exec api composer test
docker compose exec api vendor/bin/pint --test
```

Como a estrutura ainda não contém tipos de produção, a validação da #15 também usa tipos temporários para verificar a resolução PSR-4 nas quatro camadas dos seis módulos, removidos após o teste. Isso valida a convenção de localização sem introduzir classes artificiais no código versionado.

## Código do skeleton

`app/Models/User.php`, `app/Providers/AppServiceProvider.php`, o controller base e as migrations padrão permanecem em seus locais. Não há implementação de autenticação, migração do usuário para IdentityAccess ou alteração do health check nesta tarefa. Infraestrutura Docker continua fora de `bacurim-api`.

Voltar ao [README](../README.md).
