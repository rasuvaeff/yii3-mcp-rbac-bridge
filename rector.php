<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withPhpSets(php83: true)
    ->withPreparedSets(deadCode: true, codeQuality: true)
    ->withSkip([
        // `@var mixed $bound` is load-bearing: it suppresses Psalm's
        // MixedAssignment on the untyped SessionInterface::get() boundary.
        RemoveUselessVarTagRector::class,
        // The empty constructor/destructor in this fixture deliberately carry
        // #[McpTool]/#[RequiredPermission] so PermissionMapTest can assert they
        // are skipped — removing them would delete the test's whole point.
        RemoveEmptyClassMethodRector::class => [
            __DIR__ . '/tests/Support/SkippedMethodsTool.php',
        ],
    ]);
