<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\FileField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: validate() with overwriteValue = true (removes old files in a temp directory named after
 * $_SERVER['SERVER_NAME']) and the upload handling (file system access). Only the value handling is tested, with
 * $_SESSION replaced for the duration of each test.
 */
final class FileFieldValueTest extends TestCase
{
    /** @var array<array-key, mixed> */
    private array $sessionBackup = [];

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = $this->sessionBackup;
    }

    private function createField(): FileField
    {
        return new FileField(
            name: 'file',
            label: HtmlText::encoded(textContent: 'File')
        );
    }

    public function testValueIsEmptyArrayAfterConstruction(): void
    {
        $this->assertSame([], $this->createField()->getRawValue());
    }

    public function testValueIsStoredInSessionUnderTheUniquePointer(): void
    {
        $field = $this->createField();

        $this->assertSame([$field->uniqueSessFileStorePointer => []], $_SESSION);
    }

    public function testValidateWithoutOverwriteKeepsEmptyArray(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['file' => 'x'], overwriteValue: false);

        $this->assertSame([], $field->getRawValue());
    }

    public function testNullIsIgnoredAndValueStaysEmptyArray(): void
    {
        $field = $this->createField();

        $field->setValue(value: null);

        $this->assertSame([], $field->getRawValue());
    }

    public function testArrayWithoutUploadStructureIsIgnoredAndValueStaysEmptyArray(): void
    {
        $field = $this->createField();

        $field->setValue(value: ['x' => 'y']);

        $this->assertSame([], $field->getRawValue());
        $this->assertSame([], $field->getFiles());
    }
}