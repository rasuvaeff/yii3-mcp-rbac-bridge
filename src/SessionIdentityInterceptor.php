<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge;

use Mcp\Exception\ToolCallException;
use Mcp\Server\Session\SessionInterface;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallInterceptorInterface;

/**
 * Binds the MCP session to the identity that first used it and rejects any
 * request presenting the same Mcp-Session-Id with a different identity —
 * without this, a leaked session id plus the attacker's own valid token
 * rides the victim's session.
 *
 * The binding happens on the first intercepted tools/call (the core
 * interceptor chain does not see `initialize`); a guest binds as a guest.
 * Run this OUTERMOST — before RBAC and anything that trusts the session.
 *
 * @api
 */
final readonly class SessionIdentityInterceptor implements ToolCallInterceptorInterface
{
    private const string IDENTITY_KEY = 'rasuvaeff.yii3-mcp-rbac.identity';

    private const string GUEST = "\0guest";

    public function __construct(
        private IdentitySourceInterface $identitySource,
    ) {}

    #[\Override]
    public function intercept(ToolCallContext $context, callable $next): mixed
    {
        $session = $context->session;

        if (!$session instanceof SessionInterface) {
            return $next();
        }

        $current = $this->identitySource->getId() ?? self::GUEST;
        /** @var mixed $bound */
        $bound = $session->get(self::IDENTITY_KEY);

        if ($bound === null) {
            $session->set(self::IDENTITY_KEY, $current);

            return $next();
        }

        if ($bound !== $current) {
            throw new ToolCallException('MCP session is bound to a different identity; start a new session');
        }

        return $next();
    }
}
