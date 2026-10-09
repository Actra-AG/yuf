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
$header = (require __DIR__ . '/vendor/actra/coding-standard/config/php-cs-fixer-header.php')(
    copyright: 'Actra AG - https://www.actra.ch',
    license: 'MIT',
    // Code adapted from third-party libraries keeps its license (see README.md, section "License")
    thirdParty: [
        ['path' => '/src/phone/', 'license' => 'Apache-2.0'],
        ['path' => '/src/mailer/', 'license' => 'LGPL-2.1-only', 'contains' => 'PHPMailer'],
    ],
);

return new Config()
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        ...$rules,
        'header_comment' => $header['rule'],
    ])
    ->setRuleCustomisationPolicy($header['policy'])
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
