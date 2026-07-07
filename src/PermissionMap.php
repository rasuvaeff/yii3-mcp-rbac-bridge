<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

use Mcp\Capability\Attribute\McpTool;
use Rasuvaeff\Yii3McpRbacBridge\Exception\InvalidPermissionMapException;
use ReflectionClass;
use ReflectionMethod;

/**
 * tool name → required RBAC permission. Tools absent from the map are
 * unrestricted (no permission needed) — restriction is an explicit,
 * per-tool decision.
 *
 * Built from an explicit array, from #[RequiredPermission] attributes on
 * the same tool classes the server registers, or both (explicit entries
 * override attribute-derived ones).
 *
 * @api
 */
final readonly class PermissionMap
{
    /**
     * @param array<string, string> $permissions tool name => permission name
     */
    public function __construct(
        private array $permissions,
    ) {
        foreach ($permissions as $tool => $permission) {
            if ($tool === '' || $permission === '') {
                throw new InvalidPermissionMapException('Tool and permission names must not be empty');
            }
        }
    }

    /**
     * Scans the tool classes for #[McpTool] methods carrying
     * #[RequiredPermission] — the same classes listed in the yii3-mcp
     * `tools` params.
     *
     * One tool name mapped to two different permissions by attributes is a
     * configuration bug and throws — the last-one-wins silent overwrite
     * would enforce an arbitrary permission. Explicit $overrides win by
     * design and are exempt.
     *
     * @param list<class-string> $toolClasses
     * @param array<string, string> $overrides explicit entries winning over attributes
     */
    public static function fromToolClasses(array $toolClasses, array $overrides = []): self
    {
        $permissions = [];

        foreach ($toolClasses as $class) {
            if (!class_exists($class)) {
                throw new InvalidPermissionMapException(sprintf('Tool class "%s" does not exist', $class));
            }

            foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
                $required = $method->getAttributes(RequiredPermission::class);

                if ($required === []) {
                    continue;
                }

                $tools = $method->getAttributes(McpTool::class);

                if ($tools === []) {
                    throw new InvalidPermissionMapException(sprintf(
                        '#[RequiredPermission] on %s::%s() has no #[McpTool] next to it — the permission would never be enforced',
                        $class,
                        $method->getName(),
                    ));
                }

                $permission = $required[0]->newInstance()->permission;

                foreach ($tools as $tool) {
                    $name = $tool->newInstance()->name ?? $method->getName();

                    if (isset($permissions[$name]) && $permissions[$name] !== $permission) {
                        throw new InvalidPermissionMapException(sprintf(
                            'Tool "%s" maps to conflicting permissions "%s" and "%s" — one tool, one permission',
                            $name,
                            $permissions[$name],
                            $permission,
                        ));
                    }

                    $permissions[$name] = $permission;
                }
            }
        }

        return new self([...$permissions, ...$overrides]);
    }

    /**
     * @return string|null the permission required for the tool; null = unrestricted
     */
    public function permissionFor(string $toolName): ?string
    {
        return $this->permissions[$toolName] ?? null;
    }
}
