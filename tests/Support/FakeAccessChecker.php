<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests\Support;

use Stringable;
use Yiisoft\Access\AccessCheckerInterface;

final readonly class FakeAccessChecker implements AccessCheckerInterface
{
    /**
     * @param array<string, list<string>> $grants user id => granted permission names
     */
    public function __construct(
        private array $grants = [],
    ) {}

    #[\Override]
    public function userHasPermission(int|string|Stringable|null $userId, string $permissionName, array $parameters = []): bool
    {
        if ($userId === null) {
            return false;
        }

        return in_array($permissionName, $this->grants[(string) $userId] ?? [], strict: true);
    }
}
