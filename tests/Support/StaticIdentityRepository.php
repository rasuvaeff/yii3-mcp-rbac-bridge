<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Yiisoft\Auth\IdentityInterface;
use Yiisoft\Auth\IdentityRepositoryInterface;

final readonly class StaticIdentityRepository implements IdentityRepositoryInterface
{
    #[\Override]
    public function findIdentity(string $id): IdentityInterface
    {
        return new StaticIdentity($id);
    }
}
