<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\ContentType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ContentTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function forceDownloadByDefaultProvider(): iterable
    {
        yield 'html' => ['html', false];
        yield 'json' => ['json', false];
        yield 'xml' => ['xml', false];
        yield 'txt' => ['txt', false];
        yield 'csv' => ['csv', true];
        yield 'js' => ['js', false];
        yield 'css' => ['css', false];
        yield 'jpg' => ['jpg', false];
        yield 'gif' => ['gif', false];
        yield 'png' => ['png', false];
        yield 'mov' => ['mov', false];
        yield 'uppercase png' => ['PNG', false];
        yield 'pdf' => ['pdf', true];
        yield 'zip' => ['zip', true];
    }

    #[DataProvider('forceDownloadByDefaultProvider')]
    public function testForceDownloadByDefault(string $extension, bool $expectedForceDownload): void
    {
        $contentType = ContentType::createFromFileExtension(extension: $extension);

        $this->assertSame($expectedForceDownload, $contentType->forceDownloadByDefault);
    }

    public function testHtmlHasCharsetAndNoLanguageByDefault(): void
    {
        $contentType = ContentType::createHtml();

        $this->assertTrue($contentType->isHtml());
        $this->assertSame('text/html; charset=utf-8', $contentType->getHttpHeaderString());
        $this->assertNull($contentType->languageCode);
    }

    public function testHtmlKeepsTheGivenLanguage(): void
    {
        $this->assertSame('fr', ContentType::createHtml(languageCode: 'fr')->languageCode);
    }

    public function testJsonTxtAndCsvAreRecognized(): void
    {
        $this->assertTrue(ContentType::createJson()->isJson());
        $this->assertTrue(ContentType::createTxt()->isTxt());
        $this->assertTrue(ContentType::createCsv()->isCsv());
        $this->assertFalse(ContentType::createJson()->isHtml());
    }

    public function testFileExtensionIsCaseInsensitiveAndTrimmed(): void
    {
        $this->assertSame(ContentType::JSON, ContentType::createFromFileExtension(extension: ' JSON ')->type);
    }

    public function testUnknownExtensionKeepsTheTypeAndHasNoCharset(): void
    {
        $contentType = ContentType::createFromFileExtension(extension: 'pdf');

        $this->assertSame('pdf', $contentType->type);
        $this->assertNull($contentType->charset);
        $this->assertSame('application/pdf', $contentType->getHttpHeaderString());
    }
}
