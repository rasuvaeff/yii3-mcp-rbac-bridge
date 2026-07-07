<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use Mcp\Server;
use Mcp\Server\Session\InMemorySessionStore;
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

    public function invisibleToolIsAlsoRejectedOnCall(): void
    {
        $result = $this->tester(userId: null)->callTool('order.refund', ['orderId' => '7']);

        Assert::true($result['isError']);
        Assert::string($result['content'][0]['text'])->contains('not available in this session');
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

    private function server(?string $userId): Server
    {
        $visibility = new RbacToolVisibility(
            accessChecker: new FakeAccessChecker(['42' => ['orders.view']]),
            identitySource: new FixedIdentitySource($userId),
            permissions: PermissionMap::fromToolClasses([OrderTools::class]),
        );

        return (new McpServerFactory(
            container: new SimpleContainer([OrderTools::class => new OrderTools()]),
            sessionStore: new InMemorySessionStore(),
            name: 'rbac-visibility-suite',
            version: '1.0.0',
        ))->create([OrderTools::class], [], [], $visibility);
    }
}
