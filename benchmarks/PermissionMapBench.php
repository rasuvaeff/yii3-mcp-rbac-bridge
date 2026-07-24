<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Benchmarks;

use Rasuvaeff\Yii3McpRbacBridge\PermissionMap;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\OrderTools;
use Testo\Bench;

/**
 * Compares building the map (attribute reflection, done once at wiring time)
 * with resolving a permission from an already-built map (the per-call path
 * of RbacToolCallInterceptor and RbacToolVisibility).
 */
final class PermissionMapBench
{
    private static ?PermissionMap $map = null;

    #[Bench(
        callables: [
            'resolve from built map' => [self::class, 'resolvePermission'],
        ],
        calls: 10_000,
        iterations: 5,
    )]
    public function buildFromAttributes(): void
    {
        PermissionMap::fromToolClasses([OrderTools::class]);
    }

    public static function resolvePermission(): void
    {
        (self::$map ??= PermissionMap::fromToolClasses([OrderTools::class]))->permissionFor('order.status');
    }
}
