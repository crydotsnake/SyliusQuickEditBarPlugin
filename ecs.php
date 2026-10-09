<?php

declare(strict_types=1);

use Symplify\EasyCodingStandard\Config\ECSConfig;

return static function (ECSConfig $ecsConfig): void {
    $ecsConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests/Behat',
        __DIR__ . '/tests/Functional',
        __DIR__ . '/tests/Unit',
        __DIR__ . '/ecs.php',
        __DIR__ . '/rector.php',
        __DIR__ . '/.twig-cs-fixer.dist.php',
    ]);

    $ecsConfig->import('vendor/sylius-labs/coding-standard/ecs.php');
};
