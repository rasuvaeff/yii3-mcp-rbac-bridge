<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use Rasuvaeff\Yii3McpRbacBridge\StaticIdentitySource;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;

#[Test]
#[Covers(StaticIdentitySource::class)]
final readonly class StaticIdentitySourceTest
{
    public function returnsConfiguredIdentity(): void
    {
        Assert::same((new StaticIdentitySource('console-agent'))->getId(), 'console-agent');
    }

    public function defaultsToGuest(): void
    {
        Assert::null((new StaticIdentitySource())->getId());
    }
}
