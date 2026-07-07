<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use Rasuvaeff\Yii3McpRbacBridge\Exception\InvalidPermissionMapException;
use Rasuvaeff\Yii3McpRbacBridge\PermissionMap;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\ConflictingOrderTools;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\MisplacedPermissionTool;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\OrderTools;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Data\DataProvider;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(PermissionMap::class)]
final class PermissionMapTest
{
    public function readsPermissionsFromAttributes(): void
    {
        $map = PermissionMap::fromToolClasses([OrderTools::class]);

        Assert::same($map->permissionFor('order.status'), 'orders.view');
        Assert::same($map->permissionFor('order.refund'), 'orders.refund');
    }

    public function unmappedToolIsUnrestricted(): void
    {
        $map = PermissionMap::fromToolClasses([OrderTools::class]);

        Assert::null($map->permissionFor('ping'));
        Assert::null($map->permissionFor('unknown.tool'));
    }

    public function explicitOverridesWinOverAttributes(): void
    {
        $map = PermissionMap::fromToolClasses(
            [OrderTools::class],
            ['order.status' => 'orders.admin', 'ping' => 'monitoring.read'],
        );

        Assert::same($map->permissionFor('order.status'), 'orders.admin');
        Assert::same($map->permissionFor('ping'), 'monitoring.read');
        Assert::same($map->permissionFor('order.refund'), 'orders.refund');
    }

    public function conflictingAttributePermissionsForOneToolFailFast(): void
    {
        $caught = null;

        try {
            PermissionMap::fromToolClasses([OrderTools::class, ConflictingOrderTools::class]);
        } catch (InvalidPermissionMapException $caught) {
        }

        Assert::notNull($caught);
        Assert::string($caught->getMessage())
            ->contains('Tool "order.status" maps to conflicting permissions')
            ->contains('"orders.view"')
            ->contains('"orders.admin"');
    }

    public function duplicateAttributeWithSamePermissionIsTolerated(): void
    {
        $map = PermissionMap::fromToolClasses([OrderTools::class, OrderTools::class]);

        Assert::same($map->permissionFor('order.status'), 'orders.view');
    }

    public function overrideOfAConflictingToolNameStillWins(): void
    {
        // overrides are exempt from conflict detection by design
        $map = PermissionMap::fromToolClasses(
            [OrderTools::class],
            ['order.status' => 'orders.admin'],
        );

        Assert::same($map->permissionFor('order.status'), 'orders.admin');
    }

    public function requiredPermissionWithoutToolAttributeFailsFast(): void
    {
        Expect::exception(InvalidPermissionMapException::class);

        PermissionMap::fromToolClasses([MisplacedPermissionTool::class]);
    }

    public function unknownToolClassFailsFast(): void
    {
        Expect::exception(InvalidPermissionMapException::class);

        PermissionMap::fromToolClasses(['App\\Missing\\Tool']);
    }

    #[DataProvider('invalidEntryProvider')]
    public function emptyNamesAreRejected(string $tool, string $permission): void
    {
        Expect::exception(InvalidPermissionMapException::class);

        new PermissionMap([$tool => $permission]);
    }

    public static function invalidEntryProvider(): iterable
    {
        yield 'empty tool name' => ['', 'orders.view'];
        yield 'empty permission' => ['order.status', ''];
    }
}
