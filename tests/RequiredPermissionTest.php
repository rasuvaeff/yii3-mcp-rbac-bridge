<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use InvalidArgumentException;
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Expect;
use Testo\Test;

#[Test]
#[Covers(RequiredPermission::class)]
final class RequiredPermissionTest
{
    public function carriesThePermissionName(): void
    {
        Assert::same((new RequiredPermission('orders.view'))->permission, 'orders.view');
    }

    public function rejectsAnEmptyPermission(): void
    {
        Expect::exception(InvalidArgumentException::class);

        new RequiredPermission('');
    }
}
