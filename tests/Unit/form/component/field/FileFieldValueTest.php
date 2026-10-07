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
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\InMemoryFileUploadStorage;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use stdClass;

/**
 * The upload logic of the file field with the in-memory storage (the session storage has its own test). The request is
 * built by `request()` from one array of posted values and `$_FILES` entries.
 */
final class FileFieldValueTest extends TestCase
{
    private InMemoryFileUploadStorage $storage;

    #[Override]
    protected function setUp(): void
    {
        $this->storage = new InMemoryFileUploadStorage();
    }

    private function createField(
        int $maxFileUploadCount = 3,
        ?HtmlText $requiredError = null,
        ?FormMessages $messages = null,
    ): FileField {
        $field = new FileField(
            name: 'file',
            label: HtmlText::encoded(textContent: 'File'),
            requiredError: $requiredError,
            maxFileUploadCount: $maxFileUploadCount,
            storage: $this->storage,
        );
        if ($messages !== null) {
            $field->messages = $messages;
        }

        return $field;
    }

    private function storedFile(string $name = 'old.txt', string $path = '/stored/old'): UploadedFile
    {
        return new UploadedFile(name: $name, type: 'text/plain', size: 3, path: $path);
    }

    /**
     * The `$_FILES` entry of an `<input type="file" name="file[]">`.
     *
     * @param list<string> $names
     * @param list<int> $errors
     * @param list<int> $sizes
     * @return array<string, array<string, list<string|int>>>
     */
    private function uploads(array $names, array $errors = [], array $sizes = []): array
    {
        $count = count(value: $names);

        return [
            'file' => [
                'name' => $names,
                'type' => array_fill(start_index: 0, count: $count, value: 'text/plain'),
                'tmp_name' => array_map(
                    callback: static fn(int $index): string => '/tmp/php' . $index,
                    array: array_keys(array: $names),
                ),
                'error' => $errors === [] ? array_fill(start_index: 0, count: $count, value: UPLOAD_ERR_OK) : $errors,
                'size' => $sizes === [] ? array_fill(start_index: 0, count: $count, value: 10) : $sizes,
            ],
        ];
    }

    /**
     * @param array<array-key, mixed> $inputData
     * @return list<string>
     */
    private function errorsOf(FileField $field, array $inputData): array
    {
        $field->validate(input: $this->request($inputData));

        return array_map(
            callback: static fn(HtmlText $error): string => $error->render(),
            array: $field->errorCollection->listErrors(),
        );
    }

    /**
     * @return list<string>
     */
    private function fileNames(FileField $field): array
    {
        return array_values(array: array_map(
            callback: static fn(UploadedFile $file): string => $file->name,
            array: $field->getFiles(),
        ));
    }

    public function testFieldIsEmptyAfterConstruction(): void
    {
        $field = $this->createField();

        $this->assertSame([], $field->getFiles());
        $this->assertTrue($field->isValueEmpty());
        $this->assertFalse($field->valueHasChanged());
        $this->assertSame([], $field->getAddedValues());
    }

    public function testConstructionDoesNotTouchTheStorage(): void
    {
        $this->createField();

        $this->assertSame(0, $this->storage->getExpiredRemovalCount());
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testPointerIsMadeOfAllowedCharactersOnly(): void
    {
        $field = new FileField(
            name: 'my[file]-x',
            label: HtmlText::encoded(textContent: 'File'),
            storage: $this->storage,
        );

        $this->assertMatchesRegularExpression('/^[a-zA-Z\d_]+$/', $field->uniqueSessFileStorePointer);
    }

    public function testMaxFileUploadCountBelowOneIsCorrectedToOne(): void
    {
        $this->assertSame(1, $this->createField(maxFileUploadCount: 0)->maxFileUploadCount);
        $this->assertSame(1, $this->createField(maxFileUploadCount: -5)->maxFileUploadCount);
    }

    public function testLabelInfoShowsTheMaximumOfSeveralFiles(): void
    {
        $this->assertSame('(max. 3)', $this->createField(maxFileUploadCount: 3)->labelInfoText?->render());
        $this->assertNull($this->createField(maxFileUploadCount: 1)->labelInfoText);
    }

    public function testSeveralUploadsAreStored(): void
    {
        $field = $this->createField();

        $valid = $field->validate(input: $this->request($this->uploads(names: ['a.txt', 'b.txt'])));

        $this->assertTrue($valid);
        $this->assertSame(['a.txt', 'b.txt'], $this->fileNames($field));
        $this->assertCount(2, $this->storage->getStoredUploads());
        $this->assertSame(
            $field->getFiles(),
            $this->storage->getSavedFiles(pointer: $field->uniqueSessFileStorePointer),
        );
    }

    public function testSingleUploadStructureIsAccepted(): void
    {
        $field = $this->createField();

        $field->validate(input: $this->request([
            'file' => [
                'name' => ' single.txt ',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/php1',
                'error' => UPLOAD_ERR_OK,
                'size' => 4,
            ],
        ]));

        $files = array_values(array: $field->getFiles());
        $this->assertCount(1, $files);
        $this->assertSame('single.txt', $files[0]->name);
        $this->assertSame('text/plain', $files[0]->type);
        $this->assertSame(4, $files[0]->size);
    }

    public function testFilesAreKeyedByTheHashOfTheStoredPath(): void
    {
        $field = $this->createField();

        $field->validate(input: $this->request($this->uploads(names: ['a.txt'])));

        foreach ($field->getFiles() as $hash => $file) {
            $this->assertSame(hash(algo: 'sha256', data: $file->path), $hash);
            $this->assertSame($file->getHash(), $hash);
        }
    }

    public function testStoredFileIsNotNamedAfterTheClientFileName(): void
    {
        $field = $this->createField();

        $field->validate(input: $this->request($this->uploads(names: ['../../etc/passwd'])));

        $files = array_values(array: $field->getFiles());
        $this->assertArrayHasKey(0, $files);
        $file = $files[0];
        $this->assertStringNotContainsString('passwd', $file->path);
        $this->assertSame('../../etc/passwd', $file->name);
    }

    public function testEntryWithoutAFileIsIgnored(): void
    {
        $field = $this->createField();

        $valid = $field->validate(input: $this->request($this->uploads(names: [''], errors: [UPLOAD_ERR_NO_FILE], sizes: [0])));

        $this->assertTrue($valid);
        $this->assertSame([], $field->getFiles());
    }

    public function testNoFileEntryDoesNotCountForTheMaximum(): void
    {
        $field = $this->createField(maxFileUploadCount: 1);

        $field->validate(input: $this->request($this->uploads(
            names: ['', 'a.txt'],
            errors: [UPLOAD_ERR_NO_FILE, UPLOAD_ERR_OK],
            sizes: [0, 5],
        )));

        $this->assertSame(['a.txt'], $this->fileNames($field));
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function uploadErrorProvider(): iterable
    {
        yield 'ini size' => [UPLOAD_ERR_INI_SIZE, 'Die Datei war zu gross: a.txt'];
        yield 'form size' => [UPLOAD_ERR_FORM_SIZE, 'Die Datei war zu gross: a.txt'];
        yield 'partial' => [UPLOAD_ERR_PARTIAL, 'Die Datei wurde unvollständig hochgeladen: a.txt'];
        yield 'no tmp dir' => [
            UPLOAD_ERR_NO_TMP_DIR,
            'Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten: a.txt',
        ];
        yield 'cannot write' => [
            UPLOAD_ERR_CANT_WRITE,
            'Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten: a.txt',
        ];
        yield 'extension' => [
            UPLOAD_ERR_EXTENSION,
            'Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten: a.txt',
        ];
        yield 'unknown error code' => [
            99,
            'Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten: a.txt',
        ];
    }

    #[DataProvider('uploadErrorProvider')]
    public function testUploadErrorGivesTheMessageAndStoresNothing(int $error, string $expectedMessage): void
    {
        $field = $this->createField(messages: FormMessages::german());

        $errors = $this->errorsOf(
            field: $field,
            inputData: $this->uploads(names: ['a.txt'], errors: [$error], sizes: [5]),
        );

        $this->assertSame([$expectedMessage], $errors);
        $this->assertSame([], $field->getFiles());
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testUploadErrorMessagesAreEnglishByDefault(): void
    {
        $field = $this->createField();

        $errors = $this->errorsOf(
            field: $field,
            inputData: $this->uploads(names: ['a.txt'], errors: [UPLOAD_ERR_INI_SIZE], sizes: [5]),
        );

        $this->assertSame(['The file was too big: a.txt'], $errors);
    }

    public function testFileNameInTheErrorMessageIsEncoded(): void
    {
        $field = $this->createField(messages: FormMessages::german());

        $errors = $this->errorsOf(
            field: $field,
            inputData: $this->uploads(names: ['<b>"x".txt'], errors: [UPLOAD_ERR_PARTIAL], sizes: [5]),
        );

        $this->assertSame(['Die Datei wurde unvollständig hochgeladen: &lt;b&gt;&quot;x&quot;.txt'], $errors);
    }

    public function testEmptyFileIsRejected(): void
    {
        $field = $this->createField(messages: FormMessages::german());

        $errors = $this->errorsOf(field: $field, inputData: $this->uploads(names: ['a.txt'], sizes: [0]));

        $this->assertSame(['Die Datei war leer: a.txt'], $errors);
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testOtherFilesOfTheRequestAreKeptWhenOneFails(): void
    {
        $field = $this->createField();

        $valid = $field->validate(input: $this->request($this->uploads(
            names: ['big.txt', 'good.txt'],
            errors: [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_OK],
            sizes: [0, 5],
        )));

        $this->assertFalse($valid);
        $this->assertSame(['good.txt'], $this->fileNames($field));
    }

    public function testStorageThatCannotStoreTheFileGivesATechnicalError(): void
    {
        $this->storage->failStoring();
        $field = $this->createField(messages: FormMessages::german());

        $errors = $this->errorsOf(field: $field, inputData: $this->uploads(names: ['a.txt']));

        $this->assertSame(
            ['Es ist ein technischer Fehler beim Hochladen der Datei aufgetreten: a.txt'],
            $errors,
        );
        $this->assertSame([], $field->getFiles());
    }

    public function testTooManyFilesAddsNoneOfThem(): void
    {
        $field = $this->createField(maxFileUploadCount: 2, messages: FormMessages::german());
        $this->storage->preload($field->uniqueSessFileStorePointer, $this->storedFile());

        $errors = $this->errorsOf(field: $field, inputData: $this->uploads(names: ['a.txt', 'b.txt']));

        $this->assertSame(['Nur 2 Datei(en) möglich.'], $errors);
        $this->assertSame(['old.txt'], $this->fileNames($field));
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testIndividualTooManyFilesMessageReplacesTheMaximum(): void
    {
        $field = new FileField(
            name: 'file',
            label: HtmlText::encoded(textContent: 'File'),
            maxFileUploadCount: 1,
            tooManyFilesErrMsg: HtmlText::encoded(textContent: 'At most <b>[max]</b>'),
            storage: $this->storage,
        );

        $errors = $this->errorsOf(field: $field, inputData: $this->uploads(names: ['a.txt', 'b.txt']));

        $this->assertSame(['At most <b>1</b>'], $errors);
    }

    public function testFileWithTheNameOfAnUploadedFileIsRejected(): void
    {
        $field = $this->createField(messages: FormMessages::german());
        $this->storage->preload($field->uniqueSessFileStorePointer, $this->storedFile(name: 'a.txt'));

        $errors = $this->errorsOf(field: $field, inputData: $this->uploads(names: ['a.txt']));

        $this->assertSame(
            ['Es wurde bereits eine Datei mit dem Dateinamen "a.txt" hochgeladen.'],
            $errors,
        );
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testSameNameTwiceInOneRequestIsRejectedOnce(): void
    {
        $field = $this->createField();

        $errors = $this->errorsOf(field: $field, inputData: $this->uploads(names: ['a.txt', 'a.txt']));

        $this->assertSame(['A file named "a.txt" has already been uploaded.'], $errors);
        $this->assertSame(['a.txt'], $this->fileNames($field));
    }

    public function testIndividualDuplicateMessageEncodesTheFileNameOnly(): void
    {
        $field = new FileField(
            name: 'file',
            label: HtmlText::encoded(textContent: 'File'),
            maxFileUploadCount: 3,
            alreadyExistsErrorMessage: HtmlText::encoded(textContent: 'Twice <i>[fileName]</i>'),
            storage: $this->storage,
        );
        $this->storage->preload($field->uniqueSessFileStorePointer, $this->storedFile(name: '<x>.txt'));

        $errors = $this->errorsOf(field: $field, inputData: $this->uploads(names: ['<x>.txt']));

        $this->assertSame(['Twice <i>&lt;x&gt;.txt</i>'], $errors);
    }

    public function testFilesAreKeptWhenValidationFailsAndTheFormIsShownAgain(): void
    {
        $firstRequest = $this->createField();
        $firstRequest->validate(input: $this->request($this->uploads(names: ['a.txt'])));
        $pointer = $firstRequest->uniqueSessFileStorePointer;

        // The next request builds a new field; the form carries the pointer of the field as a hidden input
        $secondRequest = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));
        $valid = $secondRequest->validate(input: $this->request(['file_UID' => $pointer]));

        $this->assertTrue($valid);
        $this->assertSame(['a.txt'], $this->fileNames($secondRequest));
        $this->assertSame($pointer, $secondRequest->uniqueSessFileStorePointer);
    }

    public function testNewUploadsAreAddedToTheKeptFiles(): void
    {
        $field = $this->createField();
        $this->storage->preload('ptr', $this->storedFile());

        $field->validate(input: $this->request($this->uploads(names: ['new.txt']) + ['file_UID' => 'ptr']));

        $this->assertSame(['old.txt', 'new.txt'], $this->fileNames($field));
        $this->assertSame($field->getFiles(), $this->storage->getSavedFiles(pointer: 'ptr'));
    }

    public function testFileThatVanishedIsDropped(): void
    {
        $field = $this->createField();
        $file = $this->storedFile();
        $this->storage->preload('ptr', $file);
        $this->storage->vanish($file);

        $field->validate(input: $this->request(['file_UID' => 'ptr']));

        $this->assertSame([], $field->getFiles());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPointerProvider(): iterable
    {
        yield 'path traversal' => ['../other'];
        yield 'slash' => ['a/b'];
        yield 'dot' => ['a.b'];
        yield 'space' => ['a b'];
        yield 'empty' => [''];
        yield 'only spaces' => ['   '];
    }

    #[DataProvider('invalidPointerProvider')]
    public function testManipulatedPointerIsIgnored(string $pointer): void
    {
        $field = $this->createField();
        $ownPointer = $field->uniqueSessFileStorePointer;

        $field->validate(input: $this->request(['file_UID' => $pointer]));

        $this->assertSame($ownPointer, $field->uniqueSessFileStorePointer);
    }

    public function testPointerWithSurroundingWhitespaceIsTrimmed(): void
    {
        $field = $this->createField();

        $field->validate(input: $this->request(['file_UID' => ' abc_1 ']));

        $this->assertSame('abc_1', $field->uniqueSessFileStorePointer);
    }

    public function testPointerOfAnArrayIsIgnored(): void
    {
        $field = $this->createField();
        $ownPointer = $field->uniqueSessFileStorePointer;

        $field->validate(input: $this->request(['file_UID' => ['x']]));

        $this->assertSame($ownPointer, $field->uniqueSessFileStorePointer);
    }

    public function testRequestedFileIsRemoved(): void
    {
        $field = $this->createField();
        $first = $this->storedFile(name: 'first.txt', path: '/stored/1');
        $second = $this->storedFile(name: 'second.txt', path: '/stored/2');
        $this->storage->preload('ptr', $first, $second);

        $field->validate(input: $this->request(['file_UID' => 'ptr', 'file_removeAttachment' => $first->getHash()]));

        $this->assertSame(['second.txt'], $this->fileNames($field));
        $this->assertSame([$first], $this->storage->getDeletedFiles());
        $this->assertSame([$first->getHash()], $field->getRemovedValues());
        $this->assertSame(['second.txt'], array_map(
            callback: static fn(UploadedFile $file): string => $file->name,
            array: array_values(array: $this->storage->getSavedFiles(pointer: 'ptr')),
        ));
    }

    public function testRemovingAnUnknownFileDeletesNothingButIsReported(): void
    {
        $field = $this->createField();
        $this->storage->preload('ptr', $this->storedFile());

        $field->validate(input: $this->request(['file_UID' => 'ptr', 'file_removeAttachment' => 'unknown']));

        $this->assertSame(['old.txt'], $this->fileNames($field));
        $this->assertSame([], $this->storage->getDeletedFiles());
        $this->assertSame(['unknown'], $field->getRemovedValues());
    }

    public function testNoRemoveRequestMeansNothingWasRemoved(): void
    {
        $field = $this->createField();

        $field->validate(input: $this->request([]));

        $this->assertSame([], $field->getRemovedValues());
    }

    public function testRequestedFileIsRemovedBeforeNewFilesAreCounted(): void
    {
        $field = $this->createField(maxFileUploadCount: 1);
        $file = $this->storedFile();
        $this->storage->preload('ptr', $file);

        $field->validate(input: $this->request($this->uploads(names: ['new.txt']) + [
            'file_UID' => 'ptr',
            'file_removeAttachment' => $file->getHash(),
        ]));

        $this->assertSame(['new.txt'], $this->fileNames($field));
    }

    public function testRequiredErrorIsAddedWithoutFiles(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $this->assertSame(['Required'], $this->errorsOf(field: $field, inputData: []));
    }

    public function testRequiredIsFulfilledByAnUploadedFile(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $this->assertTrue($field->validate(input: $this->request($this->uploads(names: ['a.txt']))));
    }

    public function testRemovingTheLastFileBringsBackTheRequiredError(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));
        $file = $this->storedFile();
        $this->storage->preload('ptr', $file);

        $errors = $this->errorsOf(
            field: $field,
            inputData: ['file_UID' => 'ptr', 'file_removeAttachment' => $file->getHash()],
        );

        $this->assertSame(['Required'], $errors);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function manipulatedStructureProvider(): iterable
    {
        $valid = [
            'name' => ['a.txt'],
            'type' => ['text/plain'],
            'tmp_name' => ['/tmp/php1'],
            'error' => [0],
            'size' => [5],
        ];
        yield 'missing key' => [['name' => ['a.txt'], 'tmp_name' => ['/tmp/php1']]];
        yield 'missing size' => [array_diff_key($valid, ['size' => 1])];
        yield 'nested names' => [['name' => [['a.txt']]] + $valid];
        yield 'nested tmp names' => [['tmp_name' => [['/tmp/php1']]] + $valid];
        yield 'scalar column next to list' => [['type' => 'text/plain'] + $valid];
        yield 'list next to scalar name' => [['name' => 'a.txt'] + $valid];
        yield 'error is no int' => [['error' => ['0']] + $valid];
        yield 'error is an array' => [['error' => [[0]]] + $valid];
        yield 'size is a string' => [['size' => ['5']] + $valid];
        yield 'size is null' => [['size' => [null]] + $valid];
        yield 'name is null' => [['name' => [null]] + $valid];
        yield 'name is an int' => [['name' => [5]] + $valid];
        yield 'column of different length' => [['size' => [5, 6]] + $valid];
        yield 'column with other keys' => [['size' => ['x' => 5]] + $valid];
        yield 'object' => [['name' => new stdClass()] + $valid];
    }

    /**
     * @param array<array-key, mixed> $structure
     */
    #[DataProvider('manipulatedStructureProvider')]
    public function testManipulatedUploadStructureIsInvalidInput(array $structure): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));
        $file = $this->storedFile();
        $this->storage->preload('ptr', $file);

        $errors = $this->errorsOf(field: $field, inputData: ['file' => $structure, 'file_UID' => 'ptr']);

        $this->assertSame(['The invalid input was ignored.'], $errors);
        $this->assertSame([], $this->storage->getStoredUploads());
        $this->assertSame(['old.txt'], $this->fileNames($field));
    }

    public function testManipulatedUploadStructureUsesTheGermanMessageOfTheForm(): void
    {
        $field = $this->createField(messages: FormMessages::german());

        $errors = $this->errorsOf(field: $field, inputData: ['file' => ['name' => [['x']]]]);

        $this->assertSame(['Die ungültige Eingabe wurde ignoriert.'], $errors);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function ignoredInputProvider(): iterable
    {
        yield 'text' => ['x'];
        yield 'empty text' => [''];
        yield 'list of texts' => [['x', 'y']];
        yield 'array without upload keys' => [['x' => 'y']];
        yield 'nested array' => [[['x']]];
        yield 'int' => [5];
        yield 'null' => [null];
    }

    #[DataProvider('ignoredInputProvider')]
    public function testInputThatIsNoUploadIsIgnored(mixed $input): void
    {
        $field = $this->createField();
        $this->storage->preload('ptr', $this->storedFile());

        $valid = $field->validate(input: $this->request(['file' => $input, 'file_UID' => 'ptr']));

        $this->assertTrue($valid);
        $this->assertSame(['old.txt'], $this->fileNames($field));
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testValidationRemovesOldFilesFirst(): void
    {
        $field = $this->createField();

        $field->validate(input: $this->request([]));

        $this->assertSame(1, $this->storage->getExpiredRemovalCount());
    }

    public function testValidatingTheCurrentValueDoesNotTouchTheStorage(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $valid = $field->validateCurrentValue();

        $this->assertFalse($valid);
        $this->assertSame([], $field->getFiles());
        $this->assertSame(0, $this->storage->getExpiredRemovalCount());
        $this->assertSame([], $this->storage->getStoredUploads());
    }

    public function testRemoveOldFilesAsksTheStorage(): void
    {
        $this->createField()->removeOldFiles();

        $this->assertSame(1, $this->storage->getExpiredRemovalCount());
    }

    public function testClearDataRemovesTheFilesOfThePointer(): void
    {
        $field = $this->createField();
        $this->storage->preload('ptr', $this->storedFile());
        $field->validate(input: $this->request(['file_UID' => 'ptr']));

        $field->clearData();

        $this->assertSame(['ptr'], $this->storage->getClearedPointers());
        $this->assertSame([], $field->getFiles());
        $this->assertSame([], $this->storage->load(pointer: 'ptr'));
    }

    public function testUploadedFilesAreTheAddedValues(): void
    {
        $field = $this->createField();

        $field->validate(input: $this->request($this->uploads(names: ['a.txt', 'b.txt'])));

        $this->assertSame(array_values(array: $field->getFiles()), $field->getAddedValues());
        $this->assertTrue($field->valueHasChanged());
        $this->assertFalse($field->isValueEmpty());
    }

    public function testFieldHasNoTextToRender(): void
    {
        $this->assertSame('', $this->createField()->renderValue());
    }

    public function testFieldHasNoSetter(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: FileField::class)->hasMethod(name: 'setValue'));
    }

    public function testFieldHasNoOriginalValueSetter(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: FileField::class)->hasMethod(name: 'setOriginalValue'));
    }

    /**
     * @param array<array-key, mixed> $request Posted values and uploads in one array (`FormInput` reads both from it)
     */
    private function request(array $request): FormInput
    {
        return FormInput::fromArray(data: [], files: $request);
    }
}
