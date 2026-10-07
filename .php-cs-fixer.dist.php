<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

/** @var array<string, bool|array<string, mixed>> $rules */
$rules = require __DIR__ . '/vendor/actra/coding-standard/config/php-cs-fixer.php';

return new Config()
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        ...$rules,
        'header_comment' => [
            'header' => "@copyright Actra AG - https://www.actra.ch\n@license   MIT",
            'comment_type' => 'PHPDoc',
            'location' => 'after_open',
            'separate' => 'both',
        ],
    ])
    ->setFinder(
        Finder::create()
            ->in([
                __DIR__ . '/src',
                __DIR__ . '/tests',
                __DIR__ . '/example',
            ])
            // Generated code: phone number metadata (src/phone/data) and template cache of the example (example/app/cache)
            ->exclude(['phone/data', 'app/cache'])
            ->append([__FILE__]),
    );
