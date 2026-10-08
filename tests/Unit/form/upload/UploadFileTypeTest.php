<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\upload;

use actra\yuf\form\upload\UploadFileType;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UploadFileTypeTest extends TestCase
{
    public function testDetectedTypeAndExtensionMustBothFit(): void
    {
        $type = UploadFileType::pdf();

        $this->assertTrue($type->accepts(detectedMimeType: 'application/pdf', fileName: 'cv.pdf'));
        $this->assertFalse($type->accepts(detectedMimeType: 'image/png', fileName: 'cv.pdf'));
        $this->assertFalse($type->accepts(detectedMimeType: 'application/pdf', fileName: 'cv.png'));
    }

    public function testExtensionIsCaseInsensitive(): void
    {
        $this->assertTrue(UploadFileType::pdf()->accepts(detectedMimeType: 'application/pdf', fileName: 'CV.PDF'));
        $this->assertTrue(UploadFileType::pdf()->accepts(detectedMimeType: 'Application/PDF', fileName: 'cv.Pdf'));
    }

    public function testFileNameWithoutExtensionIsRejected(): void
    {
        $type = UploadFileType::pdf();

        $this->assertFalse($type->accepts(detectedMimeType: 'application/pdf', fileName: 'cv'));
        $this->assertFalse($type->accepts(detectedMimeType: 'application/pdf', fileName: 'cv.'));
        $this->assertFalse($type->accepts(detectedMimeType: 'application/pdf', fileName: ''));
    }

    public function testOnlyTheLastExtensionCounts(): void
    {
        $type = UploadFileType::pdf();

        $this->assertTrue($type->accepts(detectedMimeType: 'application/pdf', fileName: 'a.exe.pdf'));
        $this->assertFalse($type->accepts(detectedMimeType: 'application/pdf', fileName: 'a.pdf.exe'));
    }

    public function testEveryExtensionOfTheEntryIsAccepted(): void
    {
        $type = UploadFileType::jpeg();

        $this->assertTrue($type->accepts(detectedMimeType: 'image/jpeg', fileName: 'a.jpg'));
        $this->assertTrue($type->accepts(detectedMimeType: 'image/jpeg', fileName: 'a.jpeg'));
        $this->assertFalse($type->accepts(detectedMimeType: 'image/jpeg', fileName: 'a.jpe'));
    }

    /**
     * @return iterable<string, array{UploadFileType, string, string}>
     */
    public static function namedConstructorProvider(): iterable
    {
        yield 'pdf' => [UploadFileType::pdf(), 'application/pdf', 'a.pdf'];
        yield 'jpeg' => [UploadFileType::jpeg(), 'image/jpeg', 'a.jpg'];
        yield 'png' => [UploadFileType::png(), 'image/png', 'a.png'];
        yield 'gif' => [UploadFileType::gif(), 'image/gif', 'a.gif'];
        yield 'webp' => [UploadFileType::webp(), 'image/webp', 'a.webp'];
        yield 'plain text' => [UploadFileType::plainText(), 'text/plain', 'a.txt'];
        yield 'csv as text/csv' => [UploadFileType::csv(), 'text/csv', 'a.csv'];
        yield 'csv as text/plain' => [UploadFileType::csv(), 'text/plain', 'a.csv'];
        yield 'docx' => [
            UploadFileType::docx(),
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'a.docx',
        ];
        yield 'docx as zip' => [UploadFileType::docx(), 'application/zip', 'a.docx'];
        yield 'xlsx' => [
            UploadFileType::xlsx(),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'a.xlsx',
        ];
        yield 'xlsx as zip' => [UploadFileType::xlsx(), 'application/zip', 'a.xlsx'];
        yield 'pptx' => [
            UploadFileType::pptx(),
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'a.pptx',
        ];
        yield 'pptx as zip' => [UploadFileType::pptx(), 'application/zip', 'a.pptx'];
        yield 'zip' => [UploadFileType::zip(), 'application/zip', 'a.zip'];
    }

    #[DataProvider('namedConstructorProvider')]
    public function testNamedConstructorsAcceptTheirFiles(UploadFileType $type, string $mimeType, string $name): void
    {
        $this->assertTrue($type->accepts(detectedMimeType: $mimeType, fileName: $name));
    }

    public function testOfficeTypesDoNotAcceptAZipNamedLikeAnotherOfficeFile(): void
    {
        $this->assertFalse(UploadFileType::docx()->accepts(detectedMimeType: 'application/zip', fileName: 'a.zip'));
        $this->assertFalse(UploadFileType::zip()->accepts(detectedMimeType: 'application/zip', fileName: 'a.docx'));
    }

    public function testOwnTypeCanBeBuilt(): void
    {
        $type = new UploadFileType(mimeTypes: ['application/json'], extensions: ['json']);

        $this->assertTrue($type->accepts(detectedMimeType: 'application/json', fileName: 'a.json'));
        $this->assertSame(['application/json'], $type->mimeTypes);
        $this->assertSame(['json'], $type->extensions);
    }

    /**
     * @param list<string> $mimeTypes
     * @param list<string> $extensions
     */
    #[DataProvider('invalidTypeProvider')]
    public function testInvalidValuesAreRejected(array $mimeTypes, array $extensions): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UploadFileType(mimeTypes: $mimeTypes, extensions: $extensions);
    }

    /**
     * @return iterable<string, array{list<string>, list<string>}>
     */
    public static function invalidTypeProvider(): iterable
    {
        yield 'no MIME type' => [[], ['pdf']];
        yield 'no extension' => [['application/pdf'], []];
        yield 'MIME type in upper case' => [['Application/PDF'], ['pdf']];
        yield 'MIME type without slash' => [['pdf'], ['pdf']];
        yield 'empty MIME type' => [[''], ['pdf']];
        yield 'MIME type with space' => [['application/p df'], ['pdf']];
        yield 'extension with dot' => [['application/pdf'], ['.pdf']];
        yield 'extension in upper case' => [['application/pdf'], ['PDF']];
        yield 'empty extension' => [['application/pdf'], ['']];
        yield 'extension with a second dot' => [['application/gzip'], ['tar.gz']];
        yield 'extension with a trailing newline' => [['application/pdf'], ["pdf\n"]];
    }
}
