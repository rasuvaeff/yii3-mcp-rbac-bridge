<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

use Yiisoft\User\CurrentUser;

/**
 * Identity from yiisoft/user's CurrentUser — the id the authentication
 * middleware resolved for the current HTTP request.
 *
 * @api
 */
final readonly class CurrentUserIdentitySource implements IdentitySourceInterface
{
    public function __construct(
        private CurrentUser $currentUser,
    ) {}

    #[\Override]
    public function getId(): ?string
    {
        return $this->currentUser->isGuest() ? null : $this->currentUser->getId();
    }
}
