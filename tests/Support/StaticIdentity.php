<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Yiisoft\Auth\IdentityInterface;

final readonly class StaticIdentity implements IdentityInterface
{
    public function __construct(
        private string $id,
    ) {}

    #[\Override]
    public function getId(): string
    {
        return $this->id;
    }
}
