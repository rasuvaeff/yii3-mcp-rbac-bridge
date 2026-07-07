<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Mcp\Capability\Attribute\McpTool;
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;

final readonly class OrderTools
{
    // deliberately first and attribute-free: the permission scan must skip
    // it and still read the annotated methods below
    public function helper(): string
    {
        return 'not a tool';
    }

    /**
     * Returns the status of an order.
     */
    #[McpTool(name: 'order.status')]
    #[RequiredPermission('orders.view')]
    public function status(string $orderId): string
    {
        return 'paid:' . $orderId;
    }

    /**
     * Refunds an order.
     */
    #[McpTool(name: 'order.refund')]
    #[RequiredPermission('orders.refund')]
    public function refund(string $orderId): string
    {
        return 'refunded:' . $orderId;
    }

    /**
     * Health probe, open to everyone.
     */
    #[McpTool(name: 'ping')]
    public function ping(): string
    {
        return 'pong';
    }
}
