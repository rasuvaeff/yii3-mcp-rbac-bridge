# AGENTS.md — yii3-mcp-rbac-bridge

Guidance for AI agents working on this package. Read before changing code.

## What this is

Bridge between `rasuvaeff/yii3-mcp` and the Yii3 auth stack (namespace
`Rasuvaeff\Yii3McpRbacBridge`): per-user RBAC on MCP tool calls.
`RbacToolCallInterceptor` (yii3-mcp `ToolCallInterceptorInterface`) enforces
the permission from `PermissionMap` (built from `#[RequiredPermission]`
attributes and/or an explicit array); `RbacToolVisibility` (yii3-mcp
`ToolVisibilityInterface`) hides the same tools from `tools/list`;
`SessionIdentityInterceptor` binds the MCP session to its first identity
(anti-hijacking). Identity comes from `IdentitySourceInterface`
(`CurrentUserIdentitySource` adapter over yiisoft/user), authorization from
`Yiisoft\Access\AccessCheckerInterface` (the app binds its RBAC manager).

Public API: `RequiredPermission`, `PermissionMap`, `RbacToolCallInterceptor`,
`RbacToolVisibility`, `SessionIdentityInterceptor`, `IdentitySourceInterface`,
`CurrentUserIdentitySource`, `Exception\InvalidPermissionMapException`.

## Golden rules

1. **Verification is mandatory.** Never claim "done" without a fresh green
   `composer build`. "Should work" does not count.
2. **No suppressions.** No `@psalm-suppress`, no baseline. Fix the root cause.
3. **List and call must never disagree, and denial is fail-closed.**
   `RbacToolVisibility` and `RbacToolCallInterceptor` share one
   `PermissionMap` and one `AccessCheckerInterface` — never let them resolve
   permissions differently. Guests (null id) are DENIED on mapped tools;
   a `#[RequiredPermission]` that cannot be enforced (no `#[McpTool]` on the
   method) throws at build time, never silently no-ops.
4. **Preserve the public contract.** Update README + tests with any API change.

## Commands

No PHP/Composer on the host — run in Docker via the `composer:2` image.

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
docker run --rm -v "$PWD":/app -w /app composer:2 composer cs:fix
docker run --rm -v "$PWD":/app -w /app composer:2 composer psalm
docker run --rm -v "$PWD":/app -w /app composer:2 composer test
docker run --rm -v "$PWD":/app -w /app composer:2 composer release-check
```

Or with Make: `make build`, `make cs-fix`, `make psalm`, `make test`,
`make test-coverage`, `make mutation`, `make release-check`.

All runtime dependencies are on Packagist — a plain `composer install`
works, no path repos needed.

## Invariants & gotchas

- `PermissionMap` keys MUST be the exact names yii3-mcp registers, or the
  permission is mapped under a name no call ever carries — a silent fail-open.
  `PermissionMap::toolName()` mirrors the SDK's `ReflectedElementLoader`:
  explicit `#[McpTool(name)]` wins, else `'__invoke' === $method ? classShortName
  : methodName`. yii3-mcp registers only METHOD-level `#[McpTool]` (never
  class-level) and does NOT skip `__invoke`; it skips static/ctor/dtor. Keep
  `toolName()` in lockstep with `ReflectedElementLoader` + `McpServerFactory::register()`
  — covered by `RbacToolCallInterceptorTest::invokableToolWithoutExplicitNameIsProtected`
  (end-to-end through the real `McpTester`, would fail-open if they drift).
- Session binding happens on the FIRST `tools/call` — the yii3-mcp
  interceptor chain does not see `initialize`. Documented in README
  ("Security notes"); binding-on-initialize would need a custom
  InitializeRequest handler in the core. Don't pretend otherwise.
- The internal guest marker is `"\0guest"` — impossible as a real user id
  (contains NUL). Never replace it with a printable literal.
- `SessionIdentityInterceptor` must be OUTERMOST in the interceptor list —
  before RBAC and anything else that trusts the session.
- No `yiisoft/rbac` in `require` — the app binds `AccessCheckerInterface`
  to its manager (core-doesn't-bind-the-swappable-interface principle);
  rbac sits in `suggest`.
- Code: `declare(strict_types=1)`, `final readonly class`, `#[\Override]`,
  explicit types.
- `examples/` is part of the public contract: keep scripts runnable and update
  `examples/README.md` when example usage changes.
- **CI workflows are SHA-pinned.** Every `uses:` in `.github/workflows/*.yml`
  references a 40-char commit SHA with a `# vN` trailing comment
  (e.g. `actions/checkout@<sha> # v4`). Never revert to floating `@vN` tags.
  Updates go through Dependabot, which bumps the SHA and preserves the comment.
  Workflows also carry `permissions: { contents: read }` at workflow level and
  `persist-credentials: false` on every `actions/checkout` step. Verify with
  `zizmor --persona=auditor .github/` — must report no `unpinned-uses`,
  `excessive-permissions`, or `artipacked` findings.

## When you finish

- Update `README.md` (and `examples/` if usage changed); update `CHANGELOG.md`
  when releasing.
- Re-run `composer build`; if the change affects public API or release safety,
  also run `make release-check`. Paste the output.
