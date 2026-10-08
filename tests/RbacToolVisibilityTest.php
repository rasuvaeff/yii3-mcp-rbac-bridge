<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use Mcp\Schema\Enum\ProtocolVersion;
use Mcp\Server;
use Mcp\Server\Session\InMemorySessionStore;
use Mcp\Server\Stateless\RequestMeta;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3Mcp\McpServerFactory;
use Rasuvaeff\Yii3Mcp\Testing\McpTester;
use Rasuvaeff\Yii3McpRbacBridge\PermissionMap;
use Rasuvaeff\Yii3McpRbacBridge\RbacToolVisibility;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\FakeAccessChecker;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\FixedIdentitySource;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\OrderTools;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Test\Support\Container\SimpleContainer;

#[Test]
#[Covers(RbacToolVisibility::class)]
final class RbacToolVisibilityTest
{
    public function listingShowsOnlyPermittedAndUnrestrictedTools(): void
    {
        $names = array_column($this->tester(userId: '42')->listTools(), 'name');
        sort($names);

        Assert::same($names, ['order.status', 'ping']);
    }

    public function guestSeesOnlyUnrestrictedTools(): void
    {
        Assert::same(array_column($this->tester(userId: null)->listTools(), 'name'), ['ping']);
    }

    /**
     * The refusal's shape depends on the core: up to 3.x a tool error
     * ("not available in this session"), from 4.0 the very JSON-RPC error a
     * missing tool gets. Either way the call is refused.
     */
    public function invisibleToolIsAlsoRejectedOnCall(): void
    {
        try {
            $result = $this->tester(userId: null)->callTool('order.refund', ['orderId' => '7']);
            $refusal = ($result['isError'] ?? false) === true ? (string) ($result['content'][0]['text'] ?? '') : 'executed';
        } catch (\RuntimeException $e) {
            $refusal = $e->getMessage();
        }

        Assert::true(
            str_contains($refusal, 'not available in this session') || str_contains($refusal, 'Tool not found: "order.refund"'),
        );
    }

    /**
     * On the stateless era (yii3-mcp 4) the per-request throwaway session
     * changes nothing for RBAC: identity is read per request, so a guest is
     * still refused and a permitted user still served.
     */
    public function statelessEraKeepsTheRbacBoundary(): void
    {
        if (!class_exists(RequestMeta::class)) {
            // mcp/sdk < 0.8 (yii3-mcp < 4) has no stateless era
            Assert::same(array_column($this->tester(userId: null)->listTools(), 'name'), ['ping']);

            return;
        }

        $factory = new Psr17Factory();
        $guest = new McpTester($this->server(null, modernEra: true), $factory, $factory, $factory, ProtocolVersion::V2026_07_28);
        $user = new McpTester($this->server('42', modernEra: true), $factory, $factory, $factory, ProtocolVersion::V2026_07_28);

        Assert::same(array_column($guest->listTools(), 'name'), ['ping']);

        try {
            $guest->callTool('order.status', ['orderId' => '7']);
            $refused = false;
        } catch (\RuntimeException) {
            $refused = true;
        }

        Assert::true($refused);
        Assert::false(($user->callTool('order.status', ['orderId' => '7'])['isError'] ?? false));
    }

    private function tester(?string $userId): McpTester
    {
        $factory = new Psr17Factory();

        return new McpTester(
            server: $this->server($userId),
            requestFactory: $factory,
            responseFactory: $factory,
            streamFactory: $factory,
        );
    }

    private function server(?string $userId, bool $modernEra = false): Server
    {
        $visibility = new RbacToolVisibility(
            accessChecker: new FakeAccessChecker(['42' => ['orders.view']]),
            identitySource: new FixedIdentitySource($userId),
            permissions: PermissionMap::fromToolClasses([OrderTools::class]),
        );

        $factory = $modernEra
            ? new McpServerFactory(
                container: new SimpleContainer([OrderTools::class => new OrderTools()]),
                sessionStore: new InMemorySessionStore(),
                name: 'rbac-visibility-suite',
                version: '1.0.0',
                modernEra: true,
            )
            : new McpServerFactory(
                container: new SimpleContainer([OrderTools::class => new OrderTools()]),
                sessionStore: new InMemorySessionStore(),
                name: 'rbac-visibility-suite',
                version: '1.0.0',
            );

        return $factory->create([OrderTools::class], [], [], $visibility);
    }
}
