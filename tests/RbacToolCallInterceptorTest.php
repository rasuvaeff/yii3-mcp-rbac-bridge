<?php

declare(strict_types=1);

namespace Rasuvaeff\Yii3McpRbacBridge\Tests;

use Mcp\Server;
use Mcp\Server\Session\InMemorySessionStore;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3Mcp\McpServerFactory;
use Rasuvaeff\Yii3Mcp\Testing\McpTester;
use Rasuvaeff\Yii3McpRbacBridge\PermissionMap;
use Rasuvaeff\Yii3McpRbacBridge\RbacToolCallInterceptor;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\FakeAccessChecker;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\FixedIdentitySource;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\InvokableSecretTool;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\NamelessRegularTool;
use Rasuvaeff\Yii3McpRbacBridge\Tests\Support\OrderTools;
use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Test;
use Yiisoft\Test\Support\Container\SimpleContainer;

#[Test]
#[Covers(RbacToolCallInterceptor::class)]
final class RbacToolCallInterceptorTest
{
    public function grantedPermissionAllowsTheCall(): void
    {
        $result = $this->tester(userId: '42')->callTool('order.status', ['orderId' => '7']);

        Assert::same($result['content'][0]['text'], 'paid:7');
        Assert::false($result['isError'] ?? false);
    }

    public function missingPermissionRejectsWithToolError(): void
    {
        $result = $this->tester(userId: '42')->callTool('order.refund', ['orderId' => '7']);

        Assert::true($result['isError']);
        Assert::same($result['content'][0]['text'], 'Permission "orders.refund" is required to call tool "order.refund"');
    }

    public function guestIsDeniedOnMappedTools(): void
    {
        $result = $this->tester(userId: null)->callTool('order.status', ['orderId' => '7']);

        Assert::true($result['isError']);
        Assert::string($result['content'][0]['text'])->contains('orders.view');
    }

    public function unmappedToolPassesThroughForGuests(): void
    {
        $result = $this->tester(userId: null)->callTool('ping');

        Assert::same($result['content'][0]['text'], 'pong');
    }

    public function invokableToolWithoutExplicitNameIsProtected(): void
    {
        $factory = new Psr17Factory();
        $interceptor = new RbacToolCallInterceptor(
            accessChecker: new FakeAccessChecker(['42' => ['orders.view']]), // '42' lacks 'secret.use'
            identitySource: new FixedIdentitySource('42'),
            permissions: PermissionMap::fromToolClasses([InvokableSecretTool::class]),
        );
        $server = (new McpServerFactory(
            container: new SimpleContainer([InvokableSecretTool::class => new InvokableSecretTool()]),
            sessionStore: new InMemorySessionStore(),
            name: 'rbac-suite',
            version: '1.0.0',
        ))->create([InvokableSecretTool::class], [], [$interceptor]);
        $tester = new McpTester(server: $server, requestFactory: $factory, responseFactory: $factory, streamFactory: $factory);

        // The real pipeline registers the invokable tool under the class short name.
        Assert::same(array_column($tester->listTools(), 'name'), ['InvokableSecretTool']);

        // A user without 'secret.use' must be denied — the permission is enforced
        // under the name the pipeline actually uses, not "__invoke".
        $result = $tester->callTool('InvokableSecretTool');
        Assert::true($result['isError']);
    }

    public function regularToolWithoutExplicitNameIsProtected(): void
    {
        $factory = new Psr17Factory();
        $interceptor = new RbacToolCallInterceptor(
            accessChecker: new FakeAccessChecker(['42' => ['orders.view']]), // '42' lacks 'ledger.reconcile'
            identitySource: new FixedIdentitySource('42'),
            permissions: PermissionMap::fromToolClasses([NamelessRegularTool::class]),
        );
        $server = (new McpServerFactory(
            container: new SimpleContainer([NamelessRegularTool::class => new NamelessRegularTool()]),
            sessionStore: new InMemorySessionStore(),
            name: 'rbac-suite',
            version: '1.0.0',
        ))->create([NamelessRegularTool::class], [], [$interceptor]);
        $tester = new McpTester(server: $server, requestFactory: $factory, responseFactory: $factory, streamFactory: $factory);

        // The real pipeline registers a nameless regular tool under the method name.
        Assert::same(array_column($tester->listTools(), 'name'), ['reconcile']);

        $result = $tester->callTool('reconcile');
        Assert::true($result['isError']);
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
        $interceptor = new RbacToolCallInterceptor(
            accessChecker: new FakeAccessChecker(['42' => ['orders.view']]),
            identitySource: new FixedIdentitySource($userId),
            permissions: PermissionMap::fromToolClasses([OrderTools::class]),
        );

        return (new McpServerFactory(
            container: new SimpleContainer([OrderTools::class => new OrderTools()]),
            sessionStore: new InMemorySessionStore(),
            name: 'rbac-suite',
            version: '1.0.0',
        ))->create([OrderTools::class], [], [$interceptor]);
    }
}
