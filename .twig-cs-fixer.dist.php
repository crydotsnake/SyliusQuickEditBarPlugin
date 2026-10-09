<?php

declare(strict_types=1);

$finder = new TwigCsFixer\File\Finder();
$finder->in(__DIR__ . '/templates');

$config = new TwigCsFixer\Config\Config();
$config->setFinder($finder);

return $config;
