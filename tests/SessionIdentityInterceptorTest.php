<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use Mcp\Exception\ToolCallException;
use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Stateless\RequestMeta;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3Mcp\Interceptor\ToolCallContext;
use Rasuvaeff\Yii3Mcp\McpServerFactory;
use Rasuvaeff\Yii3Mcp\Testing\McpTester;
use Rasuvaeff\Yii3McpRbacBridge\SessionIdentityInterceptor;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\FakeSession;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\FixedIdentitySource;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\OrderTools;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Test\Support\Container\SimpleContainer;

#[Test]
#[Covers(SessionIdentityInterceptor::class)]
final class SessionIdentityInterceptorTest
{
    public function bindsTheFirstIdentityAndAcceptsItAgain(): void
    {
        $interceptor = new SessionIdentityInterceptor(new FixedIdentitySource('42'));
        $session = new FakeSession();
        $context = new ToolCallContext(toolName: 'x', arguments: [], session: $session);

        Assert::same($interceptor->intercept($context, static fn(): string => 'a'), 'a');
        Assert::same($interceptor->intercept($context, static fn(): string => 'b'), 'b');
    }

    public function differentIdentityOnABoundSessionIsRejected(): void
    {
        $source = new FixedIdentitySource('42');
        $interceptor = new SessionIdentityInterceptor($source);
        $session = new FakeSession();

        $interceptor->intercept(new ToolCallContext(toolName: 'x', arguments: [], session: $session), static fn(): string => 'a');

        $source->id = '99';
        $caught = null;

        try {
            $interceptor->intercept(new ToolCallContext(toolName: 'x', arguments: [], session: $session), static fn(): string => 'b');
        } catch (ToolCallException $caught) {
        }

        Assert::notNull($caught);
        Assert::string($caught->getMessage())->contains('bound to a different identity');
    }

    public function guestBindsAsGuestAndUserCannotTakeOver(): void
    {
        $source = new FixedIdentitySource();
        $interceptor = new SessionIdentityInterceptor($source);
        $session = new FakeSession();

        $interceptor->intercept(new ToolCallContext(toolName: 'x', arguments: [], session: $session), static fn(): string => 'a');

        $source->id = '42';
        $caught = null;

        try {
            $interceptor->intercept(new ToolCallContext(toolName: 'x', arguments: [], session: $session), static fn(): string => 'b');
        } catch (ToolCallException $caught) {
        }

        Assert::notNull($caught);
    }

    public function guestIdentityIsNotForgeableWithALiteralGuestId(): void
    {
        // a user id can never collide with the internal guest marker
        $source = new FixedIdentitySource();
        $interceptor = new SessionIdentityInterceptor($source);
        $session = new FakeSession();
        $interceptor->intercept(new ToolCallContext(toolName: 'x', arguments: [], session: $session), static fn(): string => 'a');

        $source->id = 'guest';
        $caught = null;

        try {
            $interceptor->intercept(new ToolCallContext(toolName: 'x', arguments: [], session: $session), static fn(): string => 'b');
        } catch (ToolCallException $caught) {
        }

        Assert::notNull($caught);
    }

    public function withoutASessionTheCallPassesThrough(): void
    {
        $interceptor = new SessionIdentityInterceptor(new FixedIdentitySource('42'));
        $context = new ToolCallContext(toolName: 'x', arguments: []);

        Assert::same($interceptor->intercept($context, static fn(): string => 'ok'), 'ok');
    }

    public function identitySwitchMidSessionIsRejectedEndToEnd(): void
    {
        $source = new FixedIdentitySource('42');
        $tester = $this->tester($source);

        Assert::same($tester->callTool('ping')['content'][0]['text'], 'pong');

        $source->id = '99';
        $result = $tester->callTool('ping');

        Assert::true($result['isError']);
        Assert::string($result['content'][0]['text'])->contains('bound to a different identity');
    }

    /**
     * The stateless 2026-07-28 era (yii3-mcp 4) hands every request a
     * throwaway session: there is no session to hijack, so a different
     * identity on the next request is a different caller, not a takeover —
     * it must not be rejected, and the binding is per request. Identity
     * comes from each request (the IdentitySource), exactly as RBAC reads it.
     */
    public function statelessRequestsBindPerRequestAndAreNotFalselyRejected(): void
    {
        if (!class_exists(RequestMeta::class)) {
            // mcp/sdk < 0.8 (yii3-mcp < 4) has no stateless era
            Assert::same($this->tester(new FixedIdentitySource('42'))->callTool('ping')['content'][0]['text'], 'pong');

            return;
        }

        $source = new FixedIdentitySource('42');
        $factory = new Psr17Factory();
        $tester = new McpTester($this->server($source, modernEra: true), $factory, $factory, $factory, ProtocolVersion::V2026_07_28);

        Assert::same($tester->callTool('ping')['content'][0]['text'] ?? null, 'pong');

        $source->id = '99';

        Assert::same($tester->callTool('ping')['content'][0]['text'] ?? null, 'pong');
    }

    private function tester(FixedIdentitySource $source): McpTester
    {
        $factory = new Psr17Factory();

        return new McpTester(
            server: $this->server($source),
            requestFactory: $factory,
            responseFactory: $factory,
            streamFactory: $factory,
        );
    }

    private function server(FixedIdentitySource $source, bool $modernEra = false): Server
    {
        $factory = $modernEra
            ? new McpServerFactory(
                container: new SimpleContainer([OrderTools::class => new OrderTools()]),
                sessionStore: new InMemorySessionStore(),
                name: 'identity-suite',
                version: '1.0.0',
                modernEra: true,
            )
            : new McpServerFactory(
                container: new SimpleContainer([OrderTools::class => new OrderTools()]),
                sessionStore: new InMemorySessionStore(),
                name: 'identity-suite',
                version: '1.0.0',
            );

        return $factory->create([OrderTools::class], [], [new SessionIdentityInterceptor($source)]);
    }
}
