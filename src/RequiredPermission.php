<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

use Attribute;
use InvalidArgumentException;

/**
 * Declares the RBAC permission required to call the tool defined by this
 * method. Read by {@see PermissionMap::fromToolClasses()} next to the SDK's
 * #[McpTool] attribute.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class RequiredPermission
{
    public function __construct(
        public string $permission,
    ) {
        if ($permission === '') {
            throw new InvalidArgumentException('Permission name must not be empty');
        }
    }
}
