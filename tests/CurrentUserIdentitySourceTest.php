<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use Rasuvaeff\Yii3McpRbacBridge\CurrentUserIdentitySource;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\StaticIdentity;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\StaticIdentityRepository;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Test\Support\EventDispatcher\SimpleEventDispatcher;
use Yiisoft\User\CurrentUser;

#[Test]
#[Covers(CurrentUserIdentitySource::class)]
final class CurrentUserIdentitySourceTest
{
    public function guestResolvesToNull(): void
    {
        $currentUser = new CurrentUser(new StaticIdentityRepository(), new SimpleEventDispatcher());

        Assert::null((new CurrentUserIdentitySource($currentUser))->getId());
    }

    public function authenticatedUserResolvesToItsId(): void
    {
        $currentUser = new CurrentUser(new StaticIdentityRepository(), new SimpleEventDispatcher());
        $currentUser->login(new StaticIdentity('42'));

        Assert::same((new CurrentUserIdentitySource($currentUser))->getId(), '42');
    }
}
