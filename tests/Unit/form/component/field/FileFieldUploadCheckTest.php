<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\FileField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\model\UploadedFile;
use actra\yuf\form\upload\UploadFileType;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FixedFileTypeDetector;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The type and size checks of the uploads: the detected type (a `FixedFileTypeDetector` answers instead of finfo) and
 * the extension of the client name decide, the type the client sent is ignored.
 */
final class FileFieldUploadCheckTest extends TestCase
{
    private InMemoryFileUploadStorage $storage;
    private FixedFileTypeDetector $detector;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new InMemoryFileUploadStorage();
        $this->detector = new FixedFileTypeDetector(defaultType: 'application/pdf');
    }

    /**
     * @param ?list<UploadFileType> $allowedFileTypes PDF and JPEG by default
     */
    private function createField(?array $allowedFileTypes = null, int $maxFileSize = 1000): FileField
    {
        $field = new FileField(
            name: 'file',
            label: HtmlText::fromHtml(html: 'File'),
            storage: $this->storage,
            allowedFileTypes: $allowedFileTypes ?? [UploadFileType::pdf(), UploadFileType::jpeg()],
            maxFileUploadCount: 3,
            maxFileSize: $maxFileSize,
            fileTypeDetector: $this->detector,
        );
        $field->messages = new FormMessages();

        return $field;
    }

    /**
     * @return list<string> The rendered errors
     */
    private function send(
        FileField $field,
        string $name,
        int $size = 500,
        string $clientType = 'application/pdf',
    ): array {
        $field->validate(input: FormInput::fromArray(data: [], files: [
            'file' => [
                'name' => [$name],
                'type' => [$clientType],
                'tmp_name' => ['/tmp/phpUpload'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [$size],
            ],
        ]));

        return array_map(
            callback: static fn(HtmlText $error): string => $error->render(),
            array: $field->errorCollection->listErrors(),
        );
    }

    public function testFileOfAnAllowedTypeIsAccepted(): void
    {
        $field = $this->createField();

        $errors = $this->send(field: $field, name: 'cv.pdf');

        $this->assertSame([], $errors);
        $this->assertCount(1, $field->getFiles());
        $this->assertSame(['/tmp/phpUpload'], $this->detector->getAskedPaths());
    }

    public function testSecondAllowedTypeIsAccepted(): void
    {
        $this->detector = new FixedFileTypeDetector(defaultType: 'image/jpeg');
        $field = $this->createField();

        $this->assertSame([], $this->send(field: $field, name: 'photo.jpeg'));
        $this->assertCount(1, $field->getFiles());
    }

    public function testDetectedTypeBecomesTheTypeOfTheUploadedFile(): void
    {
        $field = $this->createField();

        $this->send(field: $field, name: 'cv.pdf', clientType: 'application/x-evil');

        $files = array_values(array: $field->getFiles());
        $this->assertCount(1, $files);
        $this->assertSame('application/pdf', $files[0]->type);
        $this->assertSame(
            ['application/pdf'],
            array_values(array: array_map(
                callback: static fn(UploadedFile $file): string => $file->type,
                array: $this->storage->getSavedFiles(pointer: $field->uniqueSessFileStorePointer),
            )),
        );
    }

    public function testTypeTheClientSentIsIgnored(): void
    {
        $this->detector = new FixedFileTypeDetector(defaultType: 'text/html');
        $field = $this->createField();

        $errors = $this->send(field: $field, name: 'cv.pdf', clientType: 'application/pdf');

        $this->assertSame(['The type of the file is not allowed: cv.pdf'], $errors);
        $this->assertSame([], $field->getFiles());
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testRightTypeFromTheClientDoesNotHelpIfTheContentIsWrong(): void
    {
        $this->detector = new FixedFileTypeDetector(defaultType: 'image/png');
        $field = $this->createField();

        $this->assertSame(
            ['The type of the file is not allowed: cv.pdf'],
            $this->send(field: $field, name: 'cv.pdf', clientType: 'application/pdf'),
        );
    }

    public function testWrongExtensionIsRejected(): void
    {
        $field = $this->createField();

        $this->assertSame(
            ['The type of the file is not allowed: cv.exe'],
            $this->send(field: $field, name: 'cv.exe'),
        );
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testExtensionOfAnotherAllowedTypeDoesNotFitTheDetectedType(): void
    {
        $field = $this->createField();

        $this->assertSame(
            ['The type of the file is not allowed: photo.jpg'],
            $this->send(field: $field, name: 'photo.jpg'),
        );
    }

    public function testFileWithoutExtensionIsRejected(): void
    {
        $field = $this->createField();

        $this->assertSame(['The type of the file is not allowed: cv'], $this->send(field: $field, name: 'cv'));
    }

    public function testExtensionInUpperCaseIsAccepted(): void
    {
        $field = $this->createField();

        $this->assertSame([], $this->send(field: $field, name: 'CV.PDF'));
        $this->assertCount(1, $field->getFiles());
    }

    public function testFileThatCannotBeExaminedIsRejected(): void
    {
        $this->detector = new FixedFileTypeDetector(defaultType: null);
        $field = $this->createField();

        $this->assertSame(['The type of the file is not allowed: cv.pdf'], $this->send(field: $field, name: 'cv.pdf'));
    }

    public function testTypeMessageIsGermanWithTheGermanMessages(): void
    {
        $field = $this->createField();
        $field->messages = FormMessages::german();

        $this->assertSame(['Der Dateityp ist nicht erlaubt: cv.exe'], $this->send(field: $field, name: 'cv.exe'));
    }

    public function testFileNameInTheTypeMessageIsEncoded(): void
    {
        $field = $this->createField();

        $this->assertSame(
            ['The type of the file is not allowed: &lt;b&gt;&quot;x&quot;.exe'],
            $this->send(field: $field, name: '<b>"x".exe'),
        );
    }

    public function testFileOfTheMaximalSizeIsAccepted(): void
    {
        $field = $this->createField(maxFileSize: 1000);

        $this->assertSame([], $this->send(field: $field, name: 'cv.pdf', size: 1000));
        $this->assertCount(1, $field->getFiles());
    }

    public function testFileOneByteOverTheMaximumIsRejected(): void
    {
        $field = $this->createField(maxFileSize: 1000);

        $errors = $this->send(field: $field, name: 'cv.pdf', size: 1001);

        $this->assertSame(['The file is larger than 1000 bytes: cv.pdf'], $errors);
        $this->assertSame([], $field->getFiles());
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testSizeIsCheckedBeforeTheTypeAndTheTypeIsNotDetectedForATooLargeFile(): void
    {
        $field = $this->createField(maxFileSize: 1000);

        $errors = $this->send(field: $field, name: 'cv.exe', size: 5000);

        $this->assertSame(['The file is larger than 1000 bytes: cv.exe'], $errors);
        $this->assertSame([], $this->detector->getAskedPaths());
    }

    public function testEmptyFileIsStillReportedAsEmpty(): void
    {
        $field = $this->createField();

        $this->assertSame(['The file was empty: cv.exe'], $this->send(field: $field, name: 'cv.exe', size: 0));
    }

    private function createFieldWithDefaultMaximum(): FileField
    {
        $field = new FileField(
            name: 'file',
            label: HtmlText::fromHtml(html: 'File'),
            storage: $this->storage,
            allowedFileTypes: [UploadFileType::pdf()],
            fileTypeDetector: $this->detector,
        );
        $field->messages = new FormMessages();

        return $field;
    }

    public function testDefaultMaximumIsTenMegabytes(): void
    {
        $this->assertSame(
            [],
            $this->send(field: $this->createFieldWithDefaultMaximum(), name: 'a.pdf', size: 10 * 1024 * 1024),
        );
        $this->assertSame(
            ['The file is larger than 10 MB: b.pdf'],
            $this->send(field: $this->createFieldWithDefaultMaximum(), name: 'b.pdf', size: 10 * 1024 * 1024 + 1),
        );
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function sizeTextProvider(): iterable
    {
        yield 'one byte' => [1, '1 byte'];
        yield 'below a kilobyte' => [1023, '1023 bytes'];
        yield 'kilobyte' => [1024, '1 KB'];
        yield 'fraction of a kilobyte' => [1536, '1.5 KB'];
        yield 'megabyte' => [1024 * 1024, '1 MB'];
        yield 'ten megabytes' => [10 * 1024 * 1024, '10 MB'];
        yield 'gigabyte' => [1024 ** 3, '1 GB'];
        yield 'twenty gigabytes' => [20 * 1024 ** 3, '20 GB'];
    }

    #[DataProvider('sizeTextProvider')]
    public function testMaximumIsShownInAReadableUnit(int $maxFileSize, string $expectedText): void
    {
        $field = $this->createField(maxFileSize: $maxFileSize);

        $errors = $this->send(field: $field, name: 'cv.pdf', size: $maxFileSize + 1);

        $this->assertSame(['The file is larger than ' . $expectedText . ': cv.pdf'], $errors);
    }

    public function testTooLargeMessageIsGermanWithTheGermanMessages(): void
    {
        $field = $this->createField(maxFileSize: 2 * 1024 * 1024);
        $field->messages = FormMessages::german();

        $this->assertSame(
            ['Die Datei ist grösser als 2 MB: cv.pdf'],
            $this->send(field: $field, name: 'cv.pdf', size: 3 * 1024 * 1024),
        );
    }

    public function testOtherFilesOfTheRequestAreKeptWhenOneIsRejected(): void
    {
        $field = $this->createField();
        $this->detector->setType(path: '/tmp/phpBad', type: 'text/html');

        $field->validate(input: FormInput::fromArray(data: [], files: [
            'file' => [
                'name' => ['bad.pdf', 'good.pdf'],
                'type' => ['application/pdf', 'application/pdf'],
                'tmp_name' => ['/tmp/phpBad', '/tmp/phpGood'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [5, 5],
            ],
        ]));

        $this->assertSame(['good.pdf'], array_values(array: array_map(
            callback: static fn(UploadedFile $file): string => $file->name,
            array: $field->getFiles(),
        )));
        $this->assertCount(1, $field->errorCollection->listErrors());
    }

    public function testNoAllowedTypeIsRejectedByTheConstructor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createField(allowedFileTypes: []);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidMaxFileSizeProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    #[DataProvider('invalidMaxFileSizeProvider')]
    public function testMaximumSizeBelowOneByteIsRejectedByTheConstructor(int $maxFileSize): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createField(maxFileSize: $maxFileSize);
    }

    public function testAllowedTypesAreExposed(): void
    {
        $field = $this->createField(allowedFileTypes: [UploadFileType::png()]);

        $this->assertEquals([UploadFileType::png()], $field->allowedFileTypes);
    }
}
