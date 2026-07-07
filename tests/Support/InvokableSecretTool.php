<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Mcp\Capability\Attribute\McpTool;
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;

/**
 * Invokable single-tool class with NO explicit tool name: yii3-mcp registers it
 * under the class short name ("InvokableSecretTool"), not "__invoke". The
 * permission scan must derive the same name or the tool is left unprotected.
 */
final readonly class InvokableSecretTool
{
    #[McpTool]
    #[RequiredPermission('secret.use')]
    public function __invoke(): string
    {
        return 'top-secret';
    }
}
