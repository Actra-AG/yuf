<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Config\RuleCustomisationPolicyInterface;
use PhpCsFixer\Finder;
use PhpCsFixer\Fixer\Comment\HeaderCommentFixer;
use PhpCsFixer\Runner\Parallel\ParallelConfigFactory;

/** @var array<string, bool|array<string, mixed>> $rules */
$rules = require __DIR__ . '/vendor/actra/coding-standard/config/php-cs-fixer.php';

/**
 * @return array{header: string, comment_type: string, location: string, separate: string}
 */
function yufHeaderComment(string $license): array
{
    return [
        'header' => "@copyright Actra AG - https://www.actra.ch\n@license   " . $license,
        'comment_type' => 'PHPDoc',
        'location' => 'after_open',
        'separate' => 'both',
    ];
}

// Code adapted from third-party libraries keeps their license (see README.md, section "License")
$thirdPartyLicensePolicy = new class implements RuleCustomisationPolicyInterface {
    public function getPolicyVersionForCache(): string
    {
        return hash_file(algo: 'xxh128', filename: __FILE__);
    }

    public function getRuleCustomisers(): array
    {
        return [
            'header_comment' => static function (SplFileInfo $file): bool|HeaderCommentFixer {
                $path = str_replace(search: '\\', replace: '/', subject: $file->getPathname());
                $license = match (true) {
                    str_contains(haystack: $path, needle: '/src/phone/') => 'Apache-2.0',
                    str_contains(haystack: $path, needle: '/src/mailer/')
                        && str_contains(haystack: (string) file_get_contents(filename: $path), needle: 'PHPMailer')
                        => 'LGPL-2.1-only',
                    default => null,
                };
                if ($license === null) {
                    return true;
                }
                $fixer = new HeaderCommentFixer();
                $fixer->configure(yufHeaderComment(license: $license));

                return $fixer;
            },
        ];
    }
};

return new Config()
    ->setRiskyAllowed(true)
    ->setParallelConfig(ParallelConfigFactory::detect())
    ->setRules([
        ...$rules,
        'header_comment' => yufHeaderComment(license: 'MIT'),
    ])
    ->setRuleCustomisationPolicy($thirdPartyLicensePolicy)
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
