# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## 1.2.0 — 2026-07-27

- Compatibility with yii3-mcp 2.0 (security-hardening release, see the core's
  [UPGRADE.md](https://github.com/rasuvaeff/yii3-mcp/blob/master/UPGRADE.md)):
  constraints widened to `rasuvaeff/yii3-mcp ^1.1 || ^2.0` and
  `mcp/sdk ~0.6.0 || ~0.7.0`. No code changes — verified with a full build and
  mutation run against core 2.0.
- Document the session-ownership blind zone: core 2.0 rejects a foreign or
  ownerless session before the interceptor chain, so such rejections never
  reach this bridge's RBAC interceptor or visibility filter.
- Document how the two session bindings relate: the core's immutable
  client-owner binding at `initialize` vs this bridge's user binding on the
  first `tools/call`; on core 2.0 the `initialize` → first-call window is no
  longer open to a foreign client with a leaked session id.

## 1.1.0 — 2026-07-25

- Add `StaticIdentitySource` for explicit console/stdio identities and document
  the distinct web and CLI bindings, including guest semantics for empty roles.

- Internal: benchmark migrated to testo/bench comparison style (`#[Bench]`
  without `callables` aborts on testo/bench 0.1.6); compares map building
  (reflection) with per-call permission resolution. Dev-only, dist unchanged.

## 1.0.0 — 2026-07-07

- **Fix (fail-open):** `PermissionMap::fromToolClasses()` now derives each tool
  name exactly as yii3-mcp registers it — an invokable tool (`#[McpTool]` on
  `__invoke` with no explicit name) is keyed by its class short name, not
  `__invoke`. Previously such a tool's permission was mapped under `__invoke`
  while `tools/call`/`tools/list` used the class short name, so the permission
  was silently never enforced (the tool was callable and visible to anyone).
  Static, constructor and destructor methods are now skipped, mirroring the
  server's own registration.
- `PermissionMap::fromToolClasses()` throws `InvalidPermissionMapException`
  when attributes map one tool name to two different permissions (was a
  silent last-one-wins overwrite); explicit overrides still win by design.
- `/build` added to `.gitattributes` `export-ignore` — the empty coverage
  directory no longer ships in the dist archive.
