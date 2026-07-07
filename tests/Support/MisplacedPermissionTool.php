<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;

final readonly class MisplacedPermissionTool
{
    #[RequiredPermission('never.enforced')]
    public function helper(): string
    {
        return 'not a tool';
    }
}
