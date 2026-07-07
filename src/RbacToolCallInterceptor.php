<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

use Mcp\Exception\ToolCallException;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallInterceptorInterface;
use Yiisoft\Access\AccessCheckerInterface;

/**
 * Enforces the RBAC permission from the {@see PermissionMap} on every
 * tools/call. Tools without a mapped permission pass through; a mapped tool
 * requires the current identity to hold the permission — a guest (null id)
 * is checked as a guest and denied by any sane RBAC setup (fail-closed).
 *
 * The denial is a regular MCP tool-error envelope, so the agent sees the
 * reason instead of a transport failure.
 *
 * @api
 */
final readonly class RbacToolCallInterceptor implements ToolCallInterceptorInterface
{
    public function __construct(
        private AccessCheckerInterface $accessChecker,
        private IdentitySourceInterface $identitySource,
        private PermissionMap $permissions,
    ) {}

    #[\Override]
    public function intercept(ToolCallContext $context, callable $next): mixed
    {
        $permission = $this->permissions->permissionFor($context->toolName);

        if ($permission === null) {
            return $next();
        }

        if (!$this->accessChecker->userHasPermission($this->identitySource->getId(), $permission)) {
            throw new ToolCallException(sprintf('Permission "%s" is required to call tool "%s"', $permission, $context->toolName));
        }

        return $next();
    }
}
