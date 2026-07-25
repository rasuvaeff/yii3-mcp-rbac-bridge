<?php

declare(strict_types=1);

use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\Session\InMemorySessionStore;
use Nyholm\Psr7\Factory\Psr17Factory;
use Rasuvaeff\Yii3Mcp\McpServerFactory;
use Rasuvaeff\Yii3Mcp\Testing\McpTester;
use Rasuvaeff\Yii3McpRbacBridge\PermissionMap;
use Rasuvaeff\Yii3McpRbacBridge\RbacToolCallInterceptor;
use Rasuvaeff\Yii3McpRbacBridge\RbacToolVisibility;
use Rasuvaeff\Yii3McpRbacBridge\RequiredPermission;
use Rasuvaeff\Yii3McpRbacBridge\SessionIdentityInterceptor;
use Rasuvaeff\Yii3McpRbacBridge\StaticIdentitySource;
use Yiisoft\Access\AccessCheckerInterface;
use Yiisoft\Test\Support\Container\SimpleContainer;

require dirname(__DIR__) . '/vendor/autoload.php';

final readonly class OrderTools
{
    /**
     * Returns the status of an order.
     */
    #[McpTool(name: 'order.status')]
    #[RequiredPermission('orders.view')]
    public function status(string $orderId): string
    {
        return 'paid:' . $orderId;
    }

    /**
     * Refunds an order.
     */
    #[McpTool(name: 'order.refund')]
    #[RequiredPermission('orders.refund')]
    public function refund(string $orderId): string
    {
        return 'refunded:' . $orderId;
    }

    /**
     * Health probe, open to everyone.
     */
    #[McpTool(name: 'ping')]
    public function ping(): string
    {
        return 'pong';
    }
}

// In an application: AccessCheckerInterface = yiisoft/rbac Manager,
// IdentitySourceInterface = CurrentUserIdentitySource (yiisoft/user,
// populated by the authentication middleware). Stubs keep this offline.
$accessChecker = new class implements AccessCheckerInterface {
    #[\Override]
    public function userHasPermission(int|string|Stringable|null $userId, string $permissionName, array $parameters = []): bool
    {
        return (string) $userId === '42' && $permissionName === 'orders.view';
    }
};

$identity = new StaticIdentitySource('42');

$permissions = PermissionMap::fromToolClasses([OrderTools::class]);

$factory = new Psr17Factory();
$server = (new McpServerFactory(
    container: new SimpleContainer([OrderTools::class => new OrderTools()]),
    sessionStore: new InMemorySessionStore(),
    name: 'rbac-example',
    version: '1.0.0',
))->create(
    [OrderTools::class],
    [],
    // session binding OUTERMOST, then RBAC
    [new SessionIdentityInterceptor($identity), new RbacToolCallInterceptor($accessChecker, $identity, $permissions)],
    new RbacToolVisibility($accessChecker, $identity, $permissions),
);

$tester = new McpTester($server, $factory, $factory, $factory);

// user 42 holds orders.view only: order.refund is not even listed
echo 'tools/list: ' . implode(', ', array_column($tester->listTools(), 'name')) . "\n";

$allowed = $tester->callTool('order.status', ['orderId' => '7']);
echo 'order.status -> ' . $allowed['content'][0]['text'] . "\n";

// fail-closed on call as well (visibility rejects before RBAC even runs)
$denied = $tester->callTool('order.refund', ['orderId' => '7']);
echo 'order.refund -> isError=' . var_export($denied['isError'], true) . ': ' . $denied['content'][0]['text'] . "\n";
