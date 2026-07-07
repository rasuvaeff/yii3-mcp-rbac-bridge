<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Mcp\Capability\Attribute\McpTool;
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;

final readonly class ConflictingOrderTools
{
    /**
     * Same tool name as OrderTools::status(), different permission.
     */
    #[McpTool(name: 'order.status')]
    #[RequiredPermission('orders.admin')]
    public function status(string $orderId): string
    {
        return 'admin:' . $orderId;
    }
}
