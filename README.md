# rasuvaeff/yii3-mcp-rbac-bridge

[![Stable Version](https://img.shields.io/packagist/v/rasuvaeff/yii3-mcp-rbac-bridge?label=stable&sort_semver=1)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/rasuvaeff/yii3-mcp-rbac-bridge)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![Build](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-rbac-bridge/build.yml?branch=master)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/actions)
[![Static analysis](https://img.shields.io/github/actions/workflow/status/rasuvaeff/yii3-mcp-rbac-bridge/static-analysis.yml?branch=master&label=static%20analysis)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/actions)
[![Psalm level](https://img.shields.io/badge/psalm-level%201-141F48?logo=psalm&logoColor=white)](https://github.com/rasuvaeff/yii3-mcp-rbac-bridge/blob/master/psalm.xml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/yii3-mcp-rbac-bridge/php)](https://packagist.org/packages/rasuvaeff/yii3-mcp-rbac-bridge)
[![License](https://img.shields.io/packagist/l/rasuvaeff/yii3-mcp-rbac-bridge)](LICENSE.md)

Per-user authorization for [rasuvaeff/yii3-mcp](https://github.com/rasuvaeff/yii3-mcp)
servers over the Yii3 auth stack — the application-facing alternative to
OAuth 2.1: RBAC permissions enforced on every `tools/call`, permission-aware
`tools/list` filtering, and session-identity binding against session
hijacking.

> **Using an AI coding assistant?** [llms.txt](llms.txt) contains a compact
> API reference you can share with the model. Contributors: see [AGENTS.md](AGENTS.md).

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.3 – 8.5 |
| `rasuvaeff/yii3-mcp` | `^1.1` |
| `yiisoft/access` | `^2.0` (bind `AccessCheckerInterface` to your RBAC manager) |
| `yiisoft/user` | `^2.0` (identity of the current request) |

## Installation

```bash
composer require rasuvaeff/yii3-mcp-rbac-bridge
```

## The model: two auth layers

`SharedSecretMiddleware` (yii3-mcp) stays — it is **machine auth**: may this
MCP client talk to this endpoint at all. This bridge adds **user auth**: what
may the authenticated user behind the call actually do. Both layers run;
removing the shared secret does not follow from adding RBAC.

```php
// config/routes.php — secret first (cheap fail-closed), then identity
Route::methods(['POST', 'GET', 'DELETE', 'OPTIONS'], '/mcp')
    ->middleware(SharedSecretMiddleware::class)
    ->middleware(Authentication::class)       // yiisoft/auth: token -> CurrentUser
    ->action(McpAction::class),
```

## Usage

### 1. Declare permissions on tools

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

Restriction is explicit and per-tool: tools without a permission stay open
(behind the shared secret). `#[RequiredPermission]` on a method without
`#[McpTool]` fails the build — a permission that would never be enforced is
a bug, not a default. One tool name mapped to two different permissions by
attributes fails the build too (a silent last-one-wins would enforce an
arbitrary one); explicit overrides win by design.

### 2. Wire the bridge

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

`AccessCheckerInterface` comes from your RBAC setup (`yiisoft/rbac` manager
with `rbac-php`/`rbac-db` storage — see `suggest`).

### What each piece does

| Class | Role |
|---|---|
| `RbacToolCallInterceptor` | rejects `tools/call` without the mapped permission (regular MCP tool error, fail-closed for guests) |
| `RbacToolVisibility` | hides the same tools from `tools/list` — list and call can never disagree (one `PermissionMap`) |
| `SessionIdentityInterceptor` | binds the MCP session to its first identity; a leaked `Mcp-Session-Id` presented with another user's token is rejected |
| `PermissionMap` | tool name → permission: `#[RequiredPermission]` scan + explicit overrides |
| `CurrentUserIdentitySource` | identity id from yiisoft/user `CurrentUser` (null = guest); implement `IdentitySourceInterface` for stdio/custom setups |

## Security notes

- **Session binding happens on the first `tools/call`** (the yii3-mcp
  interceptor chain does not see `initialize`). Between `initialize` and the
  first call the session carries no identity — nothing is authorized in that
  window, so nothing is exposed; the binding covers every actual operation.
- Guests are first-class: a guest binds the session as a guest and is denied
  on every permission-mapped tool (`AccessCheckerInterface` receives `null`).
  The internal guest marker cannot be forged by a literal `"guest"` user id.
- Permission revocation applies on the next call (fail-closed). Live
  `notifications/tools/list_changed` on revocation is not part of this
  version — the SDK exposes it only with an event dispatcher, which
  yii3-mcp's factory does not yet carry.
- For stdio (`mcp:serve`) there is no HTTP request: bind
  `IdentitySourceInterface` to an env-/config-driven implementation, or
  leave the shared secret as the only (machine) identity.

## Examples

See [examples/](examples/) — runs offline.

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| [`rbac.php`](examples/rbac.php) | Filtered listing, allowed/denied calls, session binding | no |

## Development

No PHP/Composer on the host — run in Docker via the `composer:2` image:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## License

BSD-3-Clause. See [LICENSE.md](LICENSE.md).
