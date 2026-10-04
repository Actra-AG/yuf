<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class CheckboxOptionsFieldValueTest extends TestCase
{
    /**
     * @param list<string> $initialValues
     */
    private function createField(array $initialValues = []): CheckboxOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new CheckboxOptionsField(
            name: 'checkbox',
            label: HtmlText::encoded(textContent: 'Checkbox'),
            formOptions: $formOptions,
            initialValues: $initialValues
        );
    }

    public function testValueIsArrayAfterConstruction(): void
    {
        $this->assertSame(['a'], $this->createField(initialValues: ['a'])->getRawValue());
    }

    public function testValueIsEmptyArrayAfterConstructionWithEmptyArray(): void
    {
        $this->assertSame([], $this->createField()->getRawValue());
    }

    public function testArrayInputIsStored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => ['a', 'b']]);

        $this->assertTrue($isValid);
        $this->assertSame(['a', 'b'], $field->getRawValue());
    }

    /**
     * ODDITY: a posted string is accepted and stored as string (not wrapped into an array).
     */
    public function testStringInputIsStoredAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => 'a']);

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getRawValue());
    }

    public function testValueIsEmptyArrayAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame([], $field->getRawValue());
    }

    public function testUnknownOptionIsStoredButInvalid(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame(['x'], $field->getRawValue());
    }

    public function testNestedArrayIsStoredButInvalid(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => [['a']]]);

        $this->assertFalse($isValid);
        $this->assertSame([['a']], $field->getRawValue());
    }

    public function testBooleanFieldIsCheckedWithExactlyTheCheckedValue(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: false
        );

        $this->assertSame([], $field->getRawValue());
        $this->assertFalse($field->isChecked());

        $field->validate(inputData: ['boolean' => ['checked']]);

        $this->assertSame(['checked'], $field->getRawValue());
        $this->assertTrue($field->isChecked());
    }

    public function testBooleanFieldIsNotCheckedWhenStringIsPosted(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: true
        );

        $this->assertSame(['checked'], $field->getRawValue());

        $field->validate(inputData: ['boolean' => 'checked']);

        $this->assertSame('checked', $field->getRawValue());
        $this->assertFalse($field->isChecked());
    }

    public function testBooleanFieldValueIsEmptyArrayAfterValidationWithMissingKey(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: true
        );

        $field->validate(inputData: []);

        $this->assertSame([], $field->getRawValue());
    }

    public function testGetValuesReturnsConstructorValues(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(initialValues: ['a', 'b'])->getValues());
    }

    public function testGetValuesIsEmptyForEmptyConstructorArray(): void
    {
        $this->assertSame([], $this->createField()->getValues());
    }

    public function testGetValuesConvertsIntEntriesToString(): void
    {
        $field = $this->createField();
        $field->setValue(value: [1, 'a']);

        $this->assertSame(['1', 'a'], $field->getValues());
    }

    public function testGetValuesReindexesArrayAndKeepsOrder(): void
    {
        $field = $this->createField();
        $field->validate(inputData: ['checkbox' => [4 => 'b', 1 => 'a']]);

        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testGetValuesWrapsPostedString(): void
    {
        $field = $this->createField();
        $field->validate(inputData: ['checkbox' => 'a']);

        $this->assertSame(['a'], $field->getValues());
    }

    public function testGetValuesIsEmptyForPostedEmptyString(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertTrue($field->validate(inputData: ['checkbox' => '']));
        $this->assertSame([], $field->getValues());
    }

    public function testGetValuesIsEmptyAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValues: ['a']);
        $field->validate(inputData: []);

        $this->assertSame([], $field->getValues());
    }

    public function testGetValuesReturnsUnknownOptionsBecauseValidationIsResponsible(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(inputData: ['checkbox' => ['a', 'x']]));
        $this->assertSame(['a', 'x'], $field->getValues());
    }

    public function testGetValuesThrowsForNestedArrayAfterFailedValidation(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(inputData: ['checkbox' => ['a', ['b']]]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field checkbox');
        $this->expectExceptionMessage('array');

        $field->getValues();
    }

    public function testBooleanFieldGetValues(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: true
        );

        $this->assertSame(['checked'], $field->getValues());

        $field->validate(inputData: []);

        $this->assertSame([], $field->getValues());
        $this->assertFalse($field->isChecked());
    }

    public function testBooleanFieldGetValuesWithPostedString(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: false
        );
        $field->validate(inputData: ['boolean' => 'checked']);

        // isChecked() stays false for a posted string (unchanged), the list is the more tolerant view
        $this->assertSame(['checked'], $field->getValues());
        $this->assertFalse($field->isChecked());
    }

    /**
     * @return array<string, array{null|string|array<mixed>}>
     */
    public static function validInputProvider(): array
    {
        return [
            'string' => ['a'],
            'empty string' => [''],
            'array' => [['a', 'b']],
            'array with empty string' => [['']],
            'empty array' => [[]],
            'zero string is empty for validation' => [['0']],
            'empty nested array is empty for validation' => [[[]]],
            'missing key' => [null],
        ];
    }

    /**
     * @param null|string|array<mixed> $input
     */
    #[DataProvider('validInputProvider')]
    public function testGetValuesNeverThrowsAfterSuccessfulValidation(null|string|array $input): void
    {
        $field = $this->createField();
        $inputData = $input === null ? [] : ['checkbox' => $input];

        $this->assertTrue($field->validate(inputData: $inputData));
        // Must not throw: a validated field is always readable
        $field->getValues();
    }
}