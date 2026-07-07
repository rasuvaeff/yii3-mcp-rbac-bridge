<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
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
    ]);
