<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\MimeType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MimeTypeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function extensionProvider(): iterable
    {
        yield 'html' => ['html', 'text/html'];
        yield 'json' => ['json', 'application/json'];
        yield 'xml' => ['xml', 'application/xml'];
        yield 'txt' => ['txt', 'text/plain'];
        yield 'csv' => ['csv', 'text/csv'];
        yield 'js' => ['js', 'application/javascript'];
        yield 'pdf' => ['pdf', 'application/pdf'];
        yield 'png' => ['png', 'image/png'];
        yield 'uppercase' => ['PNG', 'image/png'];
        yield 'surrounding spaces' => [' zip ', 'application/zip'];
        yield 'numeric extension' => ['323', 'text/h323'];
        yield 'unknown extension' => ['zzz', 'application/octet-stream'];
        yield 'empty extension' => ['', 'application/octet-stream'];
    }

    #[DataProvider('extensionProvider')]
    public function testMimeTypeOfAFileExtension(string $extension, string $expected): void
    {
        $this->assertSame($expected, MimeType::createByFileExtension(extension: $extension)->value);
    }

    public function testNamedConstructorsUseTheConstants(): void
    {
        $this->assertSame(MimeType::HTML, MimeType::createHtml()->value);
        $this->assertSame(MimeType::JSON, MimeType::createJson()->value);
        $this->assertSame(MimeType::XML, MimeType::createXml()->value);
        $this->assertSame(MimeType::TXT, MimeType::createTxt()->value);
        $this->assertSame(MimeType::CSV, MimeType::createCsv()->value);
        $this->assertSame(MimeType::JS, MimeType::createJs()->value);
    }
}
