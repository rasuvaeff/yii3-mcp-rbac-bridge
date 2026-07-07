<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Mcp\Capability\Attribute\McpTool;
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;

/**
 * Carries #[McpTool] + #[RequiredPermission] on a constructor, a destructor and
 * a static method — none of which yii3-mcp registers as a tool. The permission
 * scan must skip them (mapping them would enforce a permission on a name no call
 * ever carries), keeping only the real instance method.
 */
final readonly class SkippedMethodsTool
{
    #[McpTool]
    #[RequiredPermission('never.construct')]
    public function __construct() {}

    #[McpTool]
    #[RequiredPermission('never.destruct')]
    public function __destruct() {}

    #[McpTool]
    #[RequiredPermission('never.static')]
    public static function factory(): string
    {
        return 'x';
    }

    #[McpTool(name: 'kept')]
    #[RequiredPermission('kept.perm')]
    public function kept(): string
    {
        return 'ok';
    }
}
