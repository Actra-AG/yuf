<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\MailerFileName;
use actra\yuf\mailer\MailerMimeTypes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailerFileNameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function pathProvider(): iterable
    {
        yield 'unix path' => ['a/b/report.pdf', 'report.pdf', 'pdf'];
        yield 'windows path' => ['C:\\x\\y.txt', 'y.txt', 'txt'];
        yield 'double extension' => ['archive.tar.gz', 'archive.tar.gz', 'gz'];
        yield 'no extension' => ['noext', 'noext', ''];
        yield 'dot file' => ['.htaccess', '.htaccess', 'htaccess'];
        yield 'trailing dot' => ['file.', 'file', ''];
        yield 'directory' => ['dir/', 'dir', ''];
        yield 'root' => ['/', '', ''];
        yield 'empty' => ['', '', ''];
        yield 'dots only' => ['..', '', ''];
        yield 'double separator' => ['a//b.txt', 'b.txt', 'txt'];
        yield 'umlauts' => ['ü/ä.PNG', 'ä.PNG', 'PNG'];
    }

    #[DataProvider('pathProvider')]
    public function testBaseNameAndExtension(string $path, string $baseName, string $extension): void
    {
        $this->assertSame($baseName, MailerFileName::baseName(path: $path));
        $this->assertSame($extension, MailerFileName::extension(path: $path));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function mimeTypeProvider(): iterable
    {
        yield 'pdf' => ['a.pdf', 'application/pdf'];
        yield 'upper case extension' => ['a.PDF', 'application/pdf'];
        yield 'jpeg' => ['x.jpeg', 'image/jpeg'];
        yield 'htm' => ['x.htm', 'text/html'];
        yield 'url with query' => ['http://example.com/y.png?v=1', 'image/png'];
        yield 'unknown extension' => ['a.unknownext', 'application/octet-stream'];
        yield 'no extension' => ['noext', 'application/octet-stream'];
    }

    #[DataProvider('mimeTypeProvider')]
    public function testFileNameToMimeType(string $fileName, string $expected): void
    {
        $this->assertSame($expected, MailerMimeTypes::getByFileName(fileName: $fileName));
    }
}
