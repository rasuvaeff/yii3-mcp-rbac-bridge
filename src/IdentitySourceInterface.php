<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

/**
 * Where the current user id comes from. In an HTTP deployment the shipped
 * {@see CurrentUserIdentitySource} adapter reads yiisoft/user's CurrentUser
 * (populated by the authentication middleware — under FPM one HTTP request
 * is one tool call, so "current" is exact). For stdio or custom setups
 * implement this with an env-/config-driven source.
 *
 * @api
 */
interface IdentitySourceInterface
{
    /**
     * @return string|null the authenticated user id; null = guest
     */
    public function getId(): ?string;
}
