# Examples

| Script | Shows | Needs server? |
|--------|-------|:-------------:|
| `rbac.php` | Permission-mapped tools: filtered `tools/list`, allowed and denied calls, session-identity binding wired outermost (stubbed access checker) | no |

Run from the package root (after `composer install`):

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/rbac.php
```
