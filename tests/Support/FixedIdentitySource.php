<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Rasuvaeff\Yii3McpRbacBridge\IdentitySourceInterface;

final class FixedIdentitySource implements IdentitySourceInterface
{
    public function __construct(
        public ?string $id = null,
    ) {}

    #[\Override]
    public function getId(): ?string
    {
        return $this->id;
    }
}
