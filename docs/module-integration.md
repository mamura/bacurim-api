# Injeção de dependências e comunicação entre módulos

Convenções da issue [#16](https://github.com/mamura/bacurim-api/issues/16). Complementam o [mapa dos contextos](context-map.md), as [camadas](architecture-layers.md) e a [estrutura modular](modular-structure.md).

## Providers e ponto de composição

Cada módulo registra suas dependências por um provider localizado em `App\Modules\<Module>\Infrastructure\Providers\<Module>ServiceProvider`, herdando de `Illuminate\Support\ServiceProvider`. Criar esse provider quando o módulo tiver bindings ou integrações reais a registrar; não criar providers vazios para preencher a estrutura.

Os providers são registrados explicitamente no array de `bootstrap/providers.php`. O autoload PSR-4 não substitui esse registro. Não haverá descoberta de módulos por varredura de diretórios nem um gerenciador genérico de módulos nesta fundação.

- `register()` declara bindings, sem consultar banco, Redis, autenticação, Request ou chamar casos de uso.
- `boot()` configura adaptadores que dependam dos providers já registrados, como rotas e listeners. Não executa operações de negócio nem carrega dados de tenant.
- O provider do módulo é dono de seus bindings; consumidores não substituem implementações de outro módulo em produção.
- `AppServiceProvider` permanece para composição da aplicação. Não concentra progressivamente todos os bindings dos módulos.
- Providers são a exceção de composição prevista na #14: podem conhecer as camadas internas e registrar entradas de Interfaces. Essa permissão não se estende a controllers ou casos de uso.

## Associar contratos e implementações

O módulo define a porta na camada consumidora, e Infrastructure fornece a implementação. Use injeção pelo construtor em casos de uso e adaptadores de entrada. Classes concretas sem dependências externas podem ser resolvidas automaticamente pelo container; não criar uma interface por classe apenas para permitir injeção.

Dentro de `register()`, a forma de binding segue o ciclo de vida:

```php
// Forma ilustrativa: substituir pelos tipos reais do módulo.
$this->app->bind(Contract::class, Adapter::class);
$this->app->scoped(ContextContract::class, ContextAdapter::class);
$this->app->singleton(StatelessContract::class, StatelessAdapter::class);
```

As três linhas representam alternativas, não bindings de produção existentes nem importações prontas para copiar. O exemplo executável e sua validação pertencem à #18.

| Ciclo | Quando usar | Cuidado |
| --- | --- | --- |
| `bind` | Padrão para portas/adaptadores e dependências que podem ser criadas por resolução. | Não pressupor que duas resoluções compartilhem estado. |
| `scoped` | Estado limitado ao ciclo de requisição ou job, quando o mecanismo de execução suporta esse ciclo. | Contexto de tenant continua explícito nas operações; validar limpeza em workers persistentes quando forem introduzidos. |
| `singleton` | Serviços comprovadamente sem estado de requisição, usuário ou tenant. | Nunca capturar tenant, usuário, Request ou serviço scoped em singleton. |

Não usar facades, `app()`, `resolve()` ou o container como service locator dentro de Domain/Application. Usar o container no bootstrap/provider e no suporte de testes é composição, não uma permissão geral para resolver dependências em qualquer classe.

O provider não deve inferir tenant a partir de uma variável global nem escolher uma implementação diferente por usuário durante o bootstrap. Os mecanismos de resolução/autorização reais serão definidos no API-M1; a #17 estabelece os princípios do contexto.

## Comunicação síncrona

O mapa admite somente estas dependências públicas entre módulos:

| Consumidor | Fornecedor | Integração |
| --- | --- | --- |
| `IdentityAccess` | `Organizations` | Consultas/operações públicas necessárias sobre o contexto de tenant/empresa. |
| `DataImport` | `Financial` | Validar e confirmar operações financeiras após preparação dos dados importados. |
| `Reporting` | `Financial` | Consultar fatos, posição e origens para interpretação e exportação. |
| `Forecasting` | `Financial` | Consultar posição e compromissos conhecidos para projeção. |

O fornecedor publica o contrato em `Application/Contracts/PublicApi` e registra a implementação no próprio provider. O consumidor recebe esse contrato por construtor e depende somente de seus tipos públicos. DTOs públicos pertencem ao mesmo limite público, para que o consumidor não importe dados internos de Application.

- Não retornar entidades mutáveis de domínio, modelos Eloquent, query builders ou coleções Laravel nesses contratos.
- Não importar repositórios, adapters, casos de uso internos ou tabelas de outro módulo, mesmo que o PHP permita o acesso.
- Não usar HTTP interno para chamadas entre módulos do mesmo monólito.
- Não criar contratos antecipados para todas as integrações do mapa; criar quando uma operação concreta exigir.
- Dados de tenant/ator cruzam a integração explicitamente, com autorização e verificação de pertencimento das referências. O contrato não aceita contexto ausente como acesso irrestrito.
- Exceções internas não formam automaticamente a API do módulo. Falhas esperadas que precisem atravessar o limite devem ter tipos/resultados públicos documentados; não expor mensagens SQL ou detalhes do framework.

Uma jornada que precise de mais de um módulo usa composição explícita de aplicação na borda do monólito. Se necessário, essa composição fica em um componente de Application fora dos módulos, recebendo seus contratos públicos. Ela não introduz dependência recíproca entre módulos e não usa repositórios internos. Criar esse componente somente quando existir um fluxo real; ainda não há um orquestrador implementado.

O contrato deve informar se a operação é atômica. Uma transação de Financial protege suas próprias invariantes. Não pressupor atomicidade global em uma sequência que chama módulos diferentes; decidir rollback, idempotência e tratamento de falhas para o fluxo concreto, sem implementar saga ou infraestrutura distribuída antecipadamente.

## Eventos de domínio e de aplicação

| Tipo | Significado e localização | Limites |
| --- | --- | --- |
| Evento de domínio | Fato relevante ocorrido no domínio, expresso como dado imutável em Domain. | Não depende do dispatcher Laravel, de jobs, ORM ou metadados HTTP. Não é automaticamente contrato público de integração. |
| Evento de aplicação | Notificação de resultado de um caso de uso ou integração, organizada em Application. | Quando consumida por outro módulo, sua representação pública pertence a `Application/Contracts/PublicApi`; Infrastructure integra com dispatcher/fila. |

Usar comunicação síncrona quando o resultado for necessário para concluir a operação ou garantir uma invariante. Considerar eventos para reações independentes quando houver um consumidor real e uma necessidade documentada de desacoplamento temporal ou funcional. Não usar eventos para esconder chamadas obrigatórias ou contornar uma dependência proibida no mapa.

Quando persistência e evento estiverem relacionados, o efeito externo só deve ser publicado após a confirmação da transação. Publicar após commit evita eventos de operações revertidas, mas não garante entrega durável entre commit e publicação. Se a operação exigir essa garantia, decidir uma estratégia como outbox antes de prometer confiabilidade. Não há outbox, broker ou mensageria distribuída nesta fundação.

Antes de introduzir processamento assíncrono, definir contrato, tenant, identificador da operação, idempotência, política de repetição e observabilidade das falhas. Jobs precisam revalidar o contexto autorizado sem depender da sessão da requisição original. Nunca incluir credenciais ou modelos ORM como conteúdo do evento público.

Um evento público novo altera as integrações previstas: registrar produtor, consumidor e direção no mapa. Eventos não eliminam dependências semânticas nem justificam ciclos entre módulos.

## Revisão e validação incremental

Na revisão de cada integração, verificar:

1. O contrato pertence ao fornecedor público ou à camada interna que precisa da porta?
2. O binding está no provider correto e seu ciclo não pode vazar estado entre tenants?
3. O consumidor usa somente os tipos públicos e respeita o mapa sem ciclos?
4. O fluxo documenta autorização, transação e falhas esperadas?
5. Um evento tem consumidor e motivo concretos, além de política de confirmação e entrega?

A #16 estabelece as convenções. O exemplo executável de resolução e camadas será validado na #18; os testes automatizados de fronteiras foram implementados na #30. A suíte Architecture verifica referências estáticas proibidas, dentro das limitações documentadas no guia de testes, sem substituir a revisão de código. O PHP não fornece privacidade de pacote para diretórios de módulos.

Voltar ao [README](../README.md).
