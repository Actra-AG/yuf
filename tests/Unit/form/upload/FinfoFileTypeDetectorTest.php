<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\upload;

use actra\yuf\form\upload\FinfoFileTypeDetector;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ZipArchive;

/**
 * Detects the types of real files, created in a temp directory. The Office files are minimal ZIP containers made the
 * way Office does it (`[Content_Types].xml` first).
 */
final class FinfoFileTypeDetectorTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-finfo-test-' . bin2hex(
            string: random_bytes(length: 8),
        );
        mkdir(directory: $this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob(pattern: $this->directory . DIRECTORY_SEPARATOR . '*');
        foreach ($files === false ? [] : $files as $file) {
            unlink(filename: $file);
        }
        rmdir(directory: $this->directory);
    }

    private function createFile(string $name, string $content): string
    {
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        file_put_contents(filename: $path, data: $content);

        return $path;
    }

    private function createZip(string $name, string $entryName): string
    {
        if (!class_exists(class: ZipArchive::class)) {
            FinfoFileTypeDetectorTest::markTestSkipped('ext-zip is needed to create the sample files.');
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . $name;
        $zip = new ZipArchive();
        $zip->open(filename: $path, flags: ZipArchive::CREATE);
        $zip->addFromString(name: '[Content_Types].xml', content: '<Types/>');
        $zip->addFromString(name: '_rels/.rels', content: '<Relationships/>');
        $zip->addFromString(name: $entryName, content: '<x/>');
        $zip->close();

        return $path;
    }

    /**
     * @return iterable<string, array{string, string, string}> Name, content (Base64), expected type
     */
    public static function fileProvider(): iterable
    {
        $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
        $png = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
        $gif = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><rect/></svg>';
        yield 'pdf' => ['a.pdf', base64_encode(string: $pdf), 'application/pdf'];
        yield 'png' => ['a.png', $png, 'image/png'];
        yield 'gif' => ['a.gif', $gif, 'image/gif'];
        yield 'plain text' => ['a.txt', base64_encode(string: "Hello world\n"), 'text/plain'];
        yield 'svg' => ['a.svg', base64_encode(string: $svg), 'image/svg+xml'];
        yield 'type does not depend on the name' => [
            'a.pdf',
            base64_encode(string: "Hello world\n"),
            'text/plain',
        ];
    }

    #[DataProvider('fileProvider')]
    public function testTypeIsDetectedFromTheContent(string $name, string $base64Content, string $expectedType): void
    {
        $path = $this->createFile(name: $name, content: (string) base64_decode(string: $base64Content, strict: true));

        $this->assertSame($expectedType, new FinfoFileTypeDetector()->detectMimeType(path: $path));
    }

    public function testCsvIsDetectedAsCsvOrPlainText(): void
    {
        $path = $this->createFile(name: 'a.csv', content: "a,b,c\n1,2,3\n4,5,6\n");

        $this->assertContains(new FinfoFileTypeDetector()->detectMimeType(path: $path), ['text/csv', 'text/plain']);
    }

    public function testOfficeFilesAreDetectedAsTheirTypeOrAsZip(): void
    {
        $expected = [
            'a.docx' => [
                'word/document.xml',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'a.xlsx' => ['xl/workbook.xml', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
            'a.pptx' => [
                'ppt/presentation.xml',
                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            ],
        ];
        foreach ($expected as $name => [$entryName, $mimeType]) {
            $detected = new FinfoFileTypeDetector()->detectMimeType(
                path: $this->createZip(name: $name, entryName: $entryName),
            );

            $this->assertContains($detected, [$mimeType, 'application/zip'], $name);
        }
    }

    public function testZipIsDetected(): void
    {
        if (!class_exists(class: ZipArchive::class)) {
            FinfoFileTypeDetectorTest::markTestSkipped('ext-zip is needed to create the sample files.');
        }
        $path = $this->directory . DIRECTORY_SEPARATOR . 'a.zip';
        $zip = new ZipArchive();
        $zip->open(filename: $path, flags: ZipArchive::CREATE);
        $zip->addFromString(name: 'x.txt', content: 'hi');
        $zip->close();

        $this->assertSame('application/zip', new FinfoFileTypeDetector()->detectMimeType(path: $path));
    }

    public function testMissingFileGivesNull(): void
    {
        $this->assertNull(new FinfoFileTypeDetector()->detectMimeType(path: $this->directory . '/missing'));
    }

    public function testDirectoryGivesNull(): void
    {
        $this->assertNull(new FinfoFileTypeDetector()->detectMimeType(path: $this->directory));
    }
}
