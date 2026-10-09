<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests/Behat',
        __DIR__ . '/tests/Functional',
        __DIR__ . '/tests/Unit',
    ])
    ->withSets([
        __DIR__ . '/vendor/sylius/sylius-rector/config/config.php',
    ])
    ->withPhpSets()
    ->withImportNames(importShortClasses: false, removeUnusedImports: true);
