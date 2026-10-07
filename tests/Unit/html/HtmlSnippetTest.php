<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\html;

use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\html\HtmlSnippet;
use actra\yuf\security\CspNonce;
use actra\yuf\tests\Double\CoreTestInstance;
use Override;
use PHPUnit\Framework\TestCase;

final class HtmlSnippetTest extends TestCase
{
    #[Override]
    public static function setUpBeforeClass(): void
    {
        $cacheDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-snippet-test' . DIRECTORY_SEPARATOR;
        if (!is_dir(filename: $cacheDirectory)) {
            mkdir(directory: $cacheDirectory);
        }
        CoreTestInstance::register(cacheDirectory: $cacheDirectory);
    }

    #[Override]
    protected function setUp(): void
    {
        // The template cache checks for its files via is_dir()/file_exists(), which would report stale results
        clearstatcache();
    }

    private static function snippetPath(): string
    {
        // Normalized, so the template cache can strip the base directory from it
        return dirname(path: __DIR__, levels: 3) . '/tests/Fixture/cspNonceSnippet.html';
    }

    public function testRendersTheGivenNonce(): void
    {
        $html = new HtmlSnippet(
            htmlSnippetFilePath: self::snippetPath(),
            cspNonce: new CspNonce(value: 'fixed+nonce=='),
        )->render();

        $this->assertStringContainsString('nonce="fixed+nonce=="', $html);
    }

    public function testNonceReplacementSetByTheCallerIsKept(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addEncodedText(identifier: 'cspNonce', content: 'own');

        $html = new HtmlSnippet(
            htmlSnippetFilePath: self::snippetPath(),
            replacements: $replacements,
            cspNonce: new CspNonce(value: 'fixed'),
        )->render();

        $this->assertStringContainsString('nonce="own"', $html);
    }

    public function testWithoutNonceNoReplacementIsAdded(): void
    {
        $snippet = new HtmlSnippet(
            htmlSnippetFilePath: dirname(path: __DIR__, levels: 3) . '/tests/Fixture/plainSnippet.html',
        );
        $snippet->replacements->addEncodedText(identifier: 'other', content: 'x');

        $html = $snippet->render();

        $this->assertStringContainsString('<p>x</p>', $html);
        $this->assertFalse($snippet->replacements->has(identifier: 'cspNonce'));
    }
}
