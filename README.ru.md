# rasuvaeff/yii3-mcp-rbac-bridge

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-mcp-rbac-bridge?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-mcp-rbac-bridge)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-rbac-bridge/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-rbac-bridge/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-mcp-rbac-bridge/php)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-mcp-rbac-bridge)](LICENSE.md)
[English version](README.md)

Постользовательская авторизация для серверов [rasuvaeff/yii3-mcp](https://github.com/rasuvaeff/yii3-mcp)
поверх Yii3-стека аутентификации — ориентированная на приложение альтернатива
OAuth 2.1: RBAC-разрешения проверяются на каждом `tools/call`, фильтрация
`tools/list` с учётом разрешений и привязка сессионной идентичности против
перехвата сессии.

> **Используете AI-ассистента?** В [llms.txt](llms.txt) — компактный
> API-справочник, которым можно поделиться с моделью. Контрибьюторам: см.
> [AGENTS.md](AGENTS.md).

## Требования

| Требование | Версия |
|-------------|---------|
| PHP | 8.3 – 8.5 |
| `rasuvaeff/yii3-mcp` | `^1.1 \|\| ^2.0` |
| `yiisoft/access` | `^2.0` (привяжите `AccessCheckerInterface` к вашему RBAC-менеджеру) |
| `yiisoft/user` | `^2.0` (идентичность текущего запроса) |

## Установка

```bash
composer require rasuvaeff/yii3-mcp-rbac-bridge
```

## Модель: два слоя аутентификации

`SharedSecretMiddleware` (из yii3-mcp) остаётся — это **машинная аутентификация**:
имеет ли данный MCP-клиент право общаться с этим эндпоинтом вообще. Этот мост
добавляет **пользовательскую аутентификацию**: что фактически может делать
аутентифицированный пользователь, инициировавший вызов. Оба слоя работают
вместе; добавление RBAC не отменяет общий секрет.

```php
// config/routes.php — secret first (cheap fail-closed), then identity
Route::methods(['POST', 'GET', 'DELETE', 'OPTIONS'], '/mcp')
    ->middleware(SharedSecretMiddleware::class)
    ->middleware(Authentication::class)       // yiisoft/auth: token -> CurrentUser
    ->action(McpAction::class),
```

## Использование

### 1. Декларирование разрешений на инструменты

```php
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;

final readonly class OrderTools
{
    #[McpTool(name: 'order.status')]
    #[RequiredPermission('orders.view')]
    public function status(string $orderId): string { ... }

    #[McpTool(name: 'ping')]          // no attribute = unrestricted
    public function ping(): string { ... }
}
```

Ограничение задаётся явно и индивидуально для каждого инструмента: инструменты без
разрешения остаются открытыми (за общим секретом). `#[RequiredPermission]` на
методе без `#[McpTool]` ломает сборку — разрешение, которое никогда не будет
применено, это баг, а не значение по умолчанию. Если одно имя инструмента через
атрибуты отображается на два разных разрешения, сборка тоже падает (молчаливый
«последний выиграл» применял бы произвольное); явные переопределения выигрывают
по дизайну.

### Имена инструментов: какими должны быть ключи карты

Ключи `PermissionMap` — это имена инструментов, и мост выводит каждое из них
ровно так же, как yii3-mcp его регистрирует, поэтому list и call никогда не
разойдутся по именам:

| Объявление инструмента | Зарегистрированное имя = ключ карты |
|---|---|
| `#[McpTool(name: 'order.status')]` | `order.status` — явное имя имеет приоритет |
| `#[McpTool]` на `public function status()` | `status` — имя метода |
| `#[McpTool]` на `public function __invoke()` | **короткое имя класса** (например, `RefundTool`), **не** `__invoke` |

`fromToolClasses()` вычисляет эти ключи за вас. Только **явная** карта (аргумент
`$overrides` или `new PermissionMap([...])`) требует самостоятельного указания
ключей — и для invokable-инструмента таким ключом служит короткое имя класса:

```php
new PermissionMap(['RefundTool' => 'orders.refund']);   // invokable RefundTool::__invoke
```

Ключ, не сопоставленный ни с одним зарегистрированным инструментом, инертен
(инструмент остаётся без ограничений), поэтому держите явные ключи в синхроне с
указанными выше именами инструментов.

### 2. Подключение моста

```php
// config/common/di/mcp-rbac.php
use Rasuvaeff\Yii3McpRbacBridge\ {
    CurrentUserIdentitySource, IdentitySourceInterface, PermissionMap,
    StaticIdentitySource,
};

return [
    IdentitySourceInterface::class => CurrentUserIdentitySource::class,
    PermissionMap::class => static fn () => PermissionMap::fromToolClasses(
        [OrderTools::class],                       // same list as the `tools` params
        // ['order.status' => 'orders.admin'],     // optional explicit overrides
    ),
];
```

Разделяйте identity binding по entry point: в stdio нет HTTP `CurrentUser`:

```php
// config/web/di.php
return [IdentitySourceInterface::class => CurrentUserIdentitySource::class];

// config/console/di.php
return [
    IdentitySourceInterface::class => static fn () => new StaticIdentitySource(
        getenv('MCP_USER_ID') ?: null,
    ),
];
```

```php
// config/params.php
'rasuvaeff/yii3-mcp' => [
    'tools' => [OrderTools::class],
    'interceptors' => [
        SessionIdentityInterceptor::class,   // outermost: binding before anything trusts the session
        RbacToolCallInterceptor::class,
    ],
    'tool_visibility' => RbacToolVisibility::class,
],
```

`AccessCheckerInterface` берётся из вашей RBAC-настройки (менеджер `yiisoft/rbac`
с хранилищем `rbac-php`/`rbac-db` — см. секцию `suggest`).

### За что отвечает каждый компонент

| Класс | Роль |
|---|---|
| `RbacToolCallInterceptor` | отклоняет `tools/call` без сопоставленного разрешения (обычная ошибка MCP-инструмента, fail-closed для гостей) |
| `RbacToolVisibility` | скрывает те же инструменты из `tools/list` — list и call никогда не разойдутся (один `PermissionMap`) |
| `SessionIdentityInterceptor` | привязывает MCP-сессию к её первой идентичности; утёкший `Mcp-Session-Id`, предъявленный с токеном другого пользователя, отклоняется |
| `PermissionMap` | имя инструмента → разрешение: сканирование `#[RequiredPermission]` + явные переопределения |
| `CurrentUserIdentitySource` | id идентичности из `CurrentUser` (yiisoft/user) (`null` = гость); реализуйте `IdentitySourceInterface` для stdio/кастомных сценариев |
| `StaticIdentitySource` | фиксированная config/env identity для console/stdio; `null` означает guest |

## Замечания по безопасности

- **Две привязки сессии, два уровня.** yii3-mcp 2.0 привязывает каждую сессию
  к **MCP-клиенту**, который её создал (immutable owner проставляется при
  `initialize`); `SessionIdentityInterceptor` этого моста привязывает сессию к
  **пользователю приложения** при первом `tools/call` (цепочка интерсепторов
  yii3-mcp не видит `initialize`). Уровни дополняют друг друга: между
  `initialize` и первым вызовом сессия по-прежнему не несёт пользовательской
  идентичности — в этом окне ничего не авторизуется, поэтому ничего и не
  раскрывается, — но на коре 2.0 чужой MCP-клиент уже не может влезть в это
  окно с утёкшим `Mcp-Session-Id`; остаётся только гонка внутри одного
  клиента. На коре 1.x клиентского уровня владения нет, и привязка
  пользователя при первом вызове — единственная защита сессии.
- **Отказы по владению сессией минуют этот мост.** yii3-mcp 2.0 отклоняет
  чужую или безвладельческую сессию (SDK-шейп 404 из `McpAction`,
  `SessionOwnershipException` из `InterceptingReferenceHandler`) ДО запуска
  цепочки интерсепторов — такой отказ не доходит ни до RBAC-интерсептора
  этого моста, ни до фильтра видимости. Попытки перехвата, остановленные
  кором, видны поэтому только в логах приложения/веб-сервера, а не в
  чём-либо, что наблюдает этот мост.
- Гости — полноправные участники: гость привязывает сессию как гость и
  отклоняется на каждом инструменте с сопоставленным разрешением
  (`AccessCheckerInterface` получает `null`). Внутренний гостевой маркер
  невозможно подделать литералом `"guest"` в качестве id пользователя.
- Отзыв разрешения применяется при следующем вызове (fail-closed). Живая
  отправка `notifications/tools/list_changed` при отзыве в эту версию не
  входит — SDK предоставляет её только с event dispatcher, который фабрика
  yii3-mcp пока не прокидывает.
- Для stdio (`mcp:serve`) HTTP-запроса нет: привяжите
  `IdentitySourceInterface` к `StaticIdentitySource`, либо
  оставьте общий секрет единственной (машинной) идентичностью.
- Guest вместе с `RbacToolVisibility` может законно получить пустой
  `tools/list`. Это результат authorization, а не поломка core `mcp:list`;
  сначала проверьте console identity binding.

## Примеры

См. [examples/](examples/) — работает офлайн.

| Скрипт | Показывает | Нужен сервер? |
|--------|-------|:-------------:|
| [`rbac.php`](examples/rbac.php) | Фильтрованный листинг, разрешённые/отклонённые вызовы, привязка сессии | нет |

### Анализаторы зависимостей

Это leaf-пакет, который root-приложение выбирает через config-plugin, поэтому в
autoloaded source может законно не быть прямой ссылки на его классы. Сохраняйте
direct dependency: backend или bridge выбирает приложение, а не core-пакет.
Исключение Composer Dependency Analyser должно быть ограничено этим пакетом:

```php
use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

return (new Configuration())->ignoreErrorsOnPackage(
    'rasuvaeff/yii3-mcp-rbac-bridge',
    [ErrorType::UNUSED_DEPENDENCY],
);
```

`composer-require-checker` ищет используемые, но не объявленные symbols, а не
unused packages, поэтому для такой config-only зависимости suppression ему не
нужен.

## Разработка

На хосте нет PHP/Composer — запускайте через Docker-образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Или через Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## Лицензия

BSD-3-Clause. См. [LICENSE.md](LICENSE.md).
