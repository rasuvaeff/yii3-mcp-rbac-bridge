# rasuvaeff/yii3-mcp-rbac-bridge
[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-mcp-rbac-bridge?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-mcp-rbac-bridge)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-rbac-bridge/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-rbac-bridge/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-mcp-rbac-bridge/php)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-mcp-rbac-bridge)](LICENSE.md)
Per-user authorization for [rasuvaeff/yii3-mcp](https://github.com/rasuvaeff/yii3-mcp)
серверы через стек аутентификации Yii3 — ориентированная на приложения альтернатива
 OAuth 2.1: разрешения RBAC применяются к каждому `tools/call`, фильтрация
 `tools/list` с учетом разрешений и привязка идентификатора сеанса против перехвата сеанса
.

 > **Используете помощника по кодированию с использованием искусственного интеллекта?** [llms.txt](llms.txt) содержит компактную ссылку
 > API, которой вы можете поделиться с моделью. Авторы: см. [AGENTS.md](AGENTS.md). @@ЛИНИЯ@@
## Требования
| Требование | Версия |
 |-------------|---------|
 | PHP | 8,3 – 8,5 |
 | `расуваефф/yii3-mcp` | `^1.1` |
 | `yiisoft/доступ` | `^2.0` (свяжите `AccessCheckerInterface` с вашим менеджером RBAC) |
 | `yiisoft/пользователь` | `^2.0` (идентификатор текущего запроса) | @@ЛИНИЯ@@
## Установка
```bash
composer require rasuvaeff/yii3-mcp-rbac-bridge
```
## Модель: два уровня аутентификации.
`SharedSecretMiddleware` (yii3-mcp) остается — это **машинная аутентификация**: может ли этот
 MCP-клиент вообще общаться с этой конечной точкой. Этот мост добавляет **аутентификацию пользователя**: что на самом деле может делать
 аутентифицированный пользователь, стоящий за вызовом. Оба слоя работают;
 удаление общего секрета не следует из добавления RBAC. @@ЛИНИЯ@@
```php
// config/routes.php — secret first (cheap fail-closed), then identity
Route::methods(['POST', 'GET', 'DELETE', 'OPTIONS'], '/mcp')
    ->middleware(SharedSecretMiddleware::class)
    ->middleware(Authentication::class)       // yiisoft/auth: token -> CurrentUser
    ->action(McpAction::class),
```
## Использование
### 1. Объявите разрешения для инструментов
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
Ограничение является явным и индивидуализировано для каждого инструмента: инструменты без разрешения остаются открытыми
 (за общим секретом). `#[RequiredPermission]` в методе без
 `#[McpTool]` завершает сборку неудачно — разрешение, которое никогда не будет применено, является
 ошибкой, а не значением по умолчанию. Одно имя инструмента, сопоставленное с двумя разными разрешениями с помощью атрибутов
, также приводит к сбою сборки (молчание «выигрывает последний» приведет к принудительному использованию произвольного
); явные переопределения выигрывают по замыслу. @@ЛИНИЯ@@
### Названия инструментов: какими должны быть ключи карты
Ключи `PermissionMap` — это имена инструментов, и мост получает каждый из них точно так, как
 yii3-mcp его регистрирует — поэтому list и call никогда не смогут связать разные имена:

 | Декларация инструмента | Зарегистрированное имя = ключ карты |
 |---|---|
 | `#[McpTool(name: 'order.status')]` | `order.status` — явное имя побеждает |
 | `#[McpTool]` в `public function status()` | `status` — имя метода |
 | `#[McpTool]` в `публичной функции __invoke()` | **короткое имя класса** (например, `RefundTool`), **не** `__invoke` |

 `fromToolClasses()` вычисляет эти ключи за вас. Только **явная** карта (аргумент
 `$overrides` или `new PermissionMap([...])`) позволяет вам правильно ввести ключ —
 для вызываемого инструмента, ключом которого является короткое имя класса:

```php
new PermissionMap(['RefundTool' => 'orders.refund']);   // invokable RefundTool::__invoke
```
Ключ, который не соответствует ни одному зарегистрированному инструменту, является инертным (инструмент остается неограниченным), поэтому
 синхронизирует явные ключи с именами инструментов, указанными выше. @@ЛИНИЯ@@
### 2. Подключите мост
```php
// config/common/di/mcp-rbac.php
use Rasuvaeff\Yii3McpRbacBridge\ {
    CurrentUserIdentitySource, IdentitySourceInterface, PermissionMap,
};

return [
    IdentitySourceInterface::class => CurrentUserIdentitySource::class,
    PermissionMap::class => static fn () => PermissionMap::fromToolClasses(
        [OrderTools::class],                       // same list as the `tools` params
        // ['order.status' => 'orders.admin'],     // optional explicit overrides
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
`AccessCheckerInterface` берется из вашей настройки RBAC (менеджер `yiisoft/rbac`
 с хранилищем `rbac-php`/`rbac-db` — см. `suggest`). @@ЛИНИЯ@@
### Что делает каждая деталь
| Класс | Роль |
 |---|---|
 | `RbacToolCallInterceptor` | отклоняет `tools/call` без сопоставленного разрешения (обычная ошибка инструмента MCP, закрытие при сбое для гостей) |
 | `RbacToolVisibility` | скрывает одни и те же инструменты из `tools/list` — список и вызов никогда не могут расходиться (один `PermissionMap`) |
 | `SessionIdentityInterceptor` | привязывает сеанс MCP к его первому идентификатору; просочившийся `Mcp-Session-Id`, представленный токеном другого пользователя, отклонен |
 | `Карта разрешений` | имя инструмента → разрешение: `#[RequiredPermission]` сканирование + явные переопределения |
 | `CurrentUserIdentitySource` | идентификатор личности из yiisoft/user `CurrentUser` (null = Guest); реализовать IdentitySourceInterface для стандартных/пользовательских настроек | @@ЛИНИЯ@@
## Примечания по безопасности
- **Привязка сеанса происходит при первом `tools/call`** (цепочка перехватчиков yii3-mcp
 не видит `initialize`). Между `initialize` и первым вызовом
 сеанс не несет никакой идентификации — в этом окне
 ничего не авторизовано, поэтому ничего не отображается; привязка охватывает каждую фактическую операцию.
 - Гости первоклассные: гость привязывает сеанс как гость, и ему запрещается
 для каждого инструмента с сопоставлением разрешений (`AccessCheckerInterface` получает `null`).
 Внутренний гостевой маркер не может быть подделан с помощью буквального идентификатора пользователя `"guest"`.
 — отзыв разрешения применяется при следующем вызове (закрытие при сбое). Live
 `notifications/tools/list_changed` при отзыве не является частью этой версии
 — SDK предоставляет его только с помощью диспетчера событий, которого
 фабрика yii3-mcp пока не поддерживает.
 — для stdio (`mcp:serve`) нет HTTP-запроса: привяжите
 `IdentitySourceInterface` к реализации, управляемой окружением/конфигурацией, или
 оставьте общий секрет в качестве единственного (машинного) идентификатора. @@ЛИНИЯ@@
## Примеры
См. [examples/](examples/) — работает в автономном режиме.

 | Скрипт | Шоу | Нужен сервер? |
 |--------|-------|:-------------:|
 | [`rbac.php`](examples/rbac.php) | Отфильтрованный список, разрешенные/запрещенные вызовы, привязка сеанса | нет | @@ЛИНИЯ@@
## Разработка
На хосте нет PHP/Composer — запустите в Docker через образ `composer:2`:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```
Или с помощью Make: make build, make cs-fix, make psalm, make test. @@ЛИНИЯ@@
## Лицензия
BSD-3-пункт. См. [LICENSE.md](LICENSE.md).
