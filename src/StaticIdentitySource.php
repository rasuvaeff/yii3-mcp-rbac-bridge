<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

/**
 * Fixed identity for stdio/config-driven deployments. Null represents guest.
 *
 * @api
 */
final readonly class StaticIdentitySource implements IdentitySourceInterface
{
    public function __construct(
        private ?string $id = null,
    ) {}

    #[\Override]
    public function getId(): ?string
    {
        return $this->id;
    }
}
