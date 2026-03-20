<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/includes',
        __DIR__ . '/alvobot-pro.php',
    ])
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/tests',
        // Procedural file — review manually
        __DIR__ . '/includes/modules/pre-article/pre-article.php',
    ])
    ->withPhpSets(php74: true)
    ->withSets([
        SetList::DEAD_CODE,
        SetList::CODE_QUALITY,
    ])
    ->withRules([
        RemoveUnusedPrivateMethodRector::class,
        RemoveUnusedPrivatePropertyRector::class,
    ])
    ->withImportNames(importDocBlockNames: false);
