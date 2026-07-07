<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

use Mcp\Schema\Tool;
use Mcp\Server\Session\SessionInterface;
use Rasuvaeff\Yii3Mcp\Visibility\ToolVisibilityInterface;
use Yiisoft\Access\AccessCheckerInterface;

/**
 * The tools/list counterpart of {@see RbacToolCallInterceptor}: a tool whose
 * mapped permission the current identity does not hold disappears from the
 * listing (and yii3-mcp's visibility layer also fail-closed rejects calling
 * it by name). Same map, same decision — list and call can never disagree.
 *
 * @api
 */
final readonly class RbacToolVisibility implements ToolVisibilityInterface
{
    public function __construct(
        private AccessCheckerInterface $accessChecker,
        private IdentitySourceInterface $identitySource,
        private PermissionMap $permissions,
    ) {}

    #[\Override]
    public function isVisible(Tool $tool, ?SessionInterface $session): bool
    {
        $permission = $this->permissions->permissionFor($tool->name);

        return $permission === null
            || $this->accessChecker->userHasPermission($this->identitySource->getId(), $permission);
    }
}
