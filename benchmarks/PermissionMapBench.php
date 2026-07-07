<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Benchmarks;

use Rasuvaeff\Yii3McpRbacBridge\PermissionMap;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\OrderTools;
use Testo\Bench;

final class PermissionMapBench
{
    private PermissionMap $map;

    public function __construct()
    {
        $this->map = PermissionMap::fromToolClasses([OrderTools::class]);
    }

    #[Bench]
    public function buildFromAttributes(): void
    {
        PermissionMap::fromToolClasses([OrderTools::class]);
    }

    #[Bench]
    public function resolvePermission(): void
    {
        $this->map->permissionFor('order.status');
    }
}
