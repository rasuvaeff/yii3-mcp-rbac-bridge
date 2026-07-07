<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Mcp\Capability\Attribute\McpTool;
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;

/**
 * Regular (non-invokable) tool method with NO explicit tool name: yii3-mcp
 * registers it under the method name ("reconcile"). The permission scan must
 * derive the same name.
 */
final readonly class NamelessRegularTool
{
    #[McpTool]
    #[RequiredPermission('ledger.reconcile')]
    public function reconcile(): string
    {
        return 'reconciled';
    }
}
