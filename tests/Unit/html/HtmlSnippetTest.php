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
use actra\yuf\template\TemplateEngine;
use actra\yuf\tests\Double\template\TemplateEngineFactory;
use Override;
use PHPUnit\Framework\TestCase;

final class HtmlSnippetTest extends TestCase
{
    private TemplateEngine $templateEngine;

    #[Override]
    protected function setUp(): void
    {
        // The template cache checks for its files via is_dir()/file_exists(), which would report stale results
        clearstatcache();
        $this->templateEngine = TemplateEngineFactory::create(
            cacheDirectory: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-snippet-test' . DIRECTORY_SEPARATOR,
            templateBaseDirectory: dirname(path: __DIR__, levels: 3) . '/',
        );
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
        )->render(templateEngine: $this->templateEngine);

        $this->assertStringContainsString('nonce="fixed+nonce=="', $html);
    }

    public function testNonceReplacementSetByTheCallerIsKept(): void
    {
        $replacements = new HtmlReplacementCollection();
        $replacements->addHtml(identifier: 'cspNonce', html: 'own');

        $html = new HtmlSnippet(
            htmlSnippetFilePath: self::snippetPath(),
            replacements: $replacements,
            cspNonce: new CspNonce(value: 'fixed'),
        )->render(templateEngine: $this->templateEngine);

        $this->assertStringContainsString('nonce="own"', $html);
    }

    public function testWithoutNonceNoReplacementIsAdded(): void
    {
        $snippet = new HtmlSnippet(
            htmlSnippetFilePath: dirname(path: __DIR__, levels: 3) . '/tests/Fixture/plainSnippet.html',
        );
        $snippet->replacements->addHtml(identifier: 'other', html: 'x');

        $html = $snippet->render(templateEngine: $this->templateEngine);

        $this->assertStringContainsString('<p>x</p>', $html);
        $this->assertFalse($snippet->replacements->has(identifier: 'cspNonce'));
    }

    public function testPlainTextReplacementIsEscaped(): void
    {
        $snippet = new HtmlSnippet(
            htmlSnippetFilePath: dirname(path: __DIR__, levels: 3) . '/tests/Fixture/plainSnippet.html',
        );
        $snippet->replacements->addText(identifier: 'other', text: '<b>');

        $this->assertStringContainsString('<p>&lt;b&gt;</p>', $snippet->render(templateEngine: $this->templateEngine));
    }
}
