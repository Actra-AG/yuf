<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class SelectOptionsFieldValueTest extends TestCase
{
    /**
     * @param null|string|list<string> $initialValue
     */
    private function createField(null|string|array $initialValue = null, bool $multiple = false): SelectOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new SelectOptionsField(
            name: 'select',
            label: HtmlText::encoded(textContent: 'Select'),
            formOptions: $formOptions,
            initialValue: $initialValue,
            acceptMultipleSelections: $multiple
        );
    }

    public function testValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('a', $this->createField(initialValue: 'a')->getRawValue());
    }

    public function testValueIsArrayAfterConstructionWithArray(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(initialValue: ['a', 'b'])->getRawValue());
    }

    public function testMultipleFieldKeepsStringInitialValueAsString(): void
    {
        $this->assertSame('a', $this->createField(initialValue: 'a', multiple: true)->getRawValue());
    }

    public function testSingleFieldStoresValidOptionAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['select' => 'a']);

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getRawValue());
    }

    public function testSingleFieldStoresEmptyStringAsValidValue(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['select' => '']);

        $this->assertTrue($isValid);
        $this->assertSame('', $field->getRawValue());
    }

    public function testSingleFieldStoresUnknownOptionButIsInvalid(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['select' => 'x']);

        $this->assertFalse($isValid);
        $this->assertSame('x', $field->getRawValue());
    }

    public function testSingleFieldValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    public function testSingleFieldRejectsArrayInputAndKeepsPreviousValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $isValid = $field->validate(inputData: ['select' => ['b']]);

        $this->assertFalse($isValid);
        $this->assertSame('a', $field->getRawValue());
    }

    public function testSingleFieldConstructedWithArrayAcceptsArrayInput(): void
    {
        $field = $this->createField(initialValue: ['a']);

        $isValid = $field->validate(inputData: ['select' => ['a', 'b']]);

        $this->assertTrue($isValid);
        $this->assertSame(['a', 'b'], $field->getRawValue());
    }

    public function testMultipleFieldStoresArrayInput(): void
    {
        $field = $this->createField(multiple: true);

        $isValid = $field->validate(inputData: ['select' => ['a', 'b']]);

        $this->assertTrue($isValid);
        $this->assertSame(['a', 'b'], $field->getRawValue());
    }

    /**
     * ODDITY: unlike ToggleField, a multiple SelectOptionsField keeps a posted string as string (not wrapped).
     */
    public function testMultipleFieldStoresStringInputAsString(): void
    {
        $field = $this->createField(multiple: true);

        $isValid = $field->validate(inputData: ['select' => 'a']);

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getRawValue());
    }

    public function testMultipleFieldValueIsEmptyArrayAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValue: 'a', multiple: true);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame([], $field->getRawValue());
    }

    public function testMultipleFieldStoresUnknownOptionsButIsInvalid(): void
    {
        $field = $this->createField(multiple: true);

        $isValid = $field->validate(inputData: ['select' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame(['x'], $field->getRawValue());
    }

    public function testMultipleFieldStoresNestedArrayButIsInvalid(): void
    {
        $field = $this->createField(multiple: true);

        $isValid = $field->validate(inputData: ['select' => [['a']]]);

        $this->assertFalse($isValid);
        $this->assertSame([['a']], $field->getRawValue());
    }

    public function testGetValueAsStringIsEmptyForNull(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testGetValueAsStringReturnsConstructorString(): void
    {
        $this->assertSame('a', $this->createField(initialValue: 'a')->getValueAsString());
    }

    public function testGetValueAsStringReturnsPostedString(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['select' => 'b']);

        $this->assertSame('b', $field->getValueAsString());
    }

    public function testGetValueAsStringIsEmptyAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->validate(inputData: []);

        $this->assertSame('', $field->getValueAsString());
    }

    public function testGetValueAsStringReturnsPreviousValueAfterRejectedArrayInput(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->validate(inputData: ['select' => ['b']]);

        $this->assertSame('a', $field->getValueAsString());
    }

    public function testGetValueAsStringThrowsForArrayConstructorValue(): void
    {
        $field = $this->createField(initialValue: ['a']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field select');
        $this->expectExceptionMessage('array');

        $field->getValueAsString();
    }

    public function testGetValueAsStringThrowsForMultipleFieldWithPostedArray(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['select' => ['a', 'b']]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field select');

        $field->getValueAsString();
    }

    public function testGetValueAsStringThrowsForMultipleFieldWithStringInitialValue(): void
    {
        $field = $this->createField(initialValue: 'a', multiple: true);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('multiple selection field');

        $field->getValueAsString();
    }

    public function testGetValueAsStringThrowsForMultipleFieldWithPostedString(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['select' => 'a']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('multiple selection field');

        $field->getValueAsString();
    }

    public function testGetValueAsStringThrowsForMultipleFieldWithMissingKey(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: []);

        $this->expectException(UnexpectedValueException::class);

        $field->getValueAsString();
    }

    public function testGetValuesIsEmptyForNull(): void
    {
        $this->assertSame([], $this->createField()->getValues());
    }

    public function testGetValuesIsEmptyForEmptyString(): void
    {
        $this->assertSame([], $this->createField(initialValue: '')->getValues());
    }

    public function testGetValuesReturnsListWithStringInitialValue(): void
    {
        $this->assertSame(['a'], $this->createField(initialValue: 'a')->getValues());
    }

    public function testGetValuesWrapsStringInitialValueOfMultipleField(): void
    {
        $this->assertSame(['a'], $this->createField(initialValue: 'a', multiple: true)->getValues());
    }

    public function testGetValuesReturnsArrayInitialValue(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(initialValue: ['a', 'b'], multiple: true)->getValues());
    }

    public function testGetValuesReindexesArrayAndKeepsOrder(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['select' => [5 => 'b', 3 => 'a']]);

        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testGetValuesConvertsIntEntriesToString(): void
    {
        $field = $this->createField(multiple: true);
        $field->setValue(value: [1, 'a']);

        $this->assertSame(['1', 'a'], $field->getValues());
    }

    public function testGetValuesDropsEmptyEntries(): void
    {
        $field = $this->createField(multiple: true);
        $field->setValue(value: ['', null, 'a', false, [], 0.0, '0']);

        $this->assertSame(['a', '0'], $field->getValues());
    }

    public function testGetValuesReturnsPostedString(): void
    {
        $field = $this->createField();
        $field->validate(inputData: ['select' => 'b']);

        $this->assertSame(['b'], $field->getValues());
    }

    public function testGetValuesIsEmptyForPostedEmptyString(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->validate(inputData: ['select' => '']));
        $this->assertSame([], $field->getValues());
    }

    public function testGetValuesIsEmptyAfterValidationWithMissingKey(): void
    {
        $single = $this->createField(initialValue: 'a');
        $single->validate(inputData: []);
        $multiple = $this->createField(initialValue: ['a'], multiple: true);
        $multiple->validate(inputData: []);

        $this->assertSame([], $single->getValues());
        $this->assertSame([], $multiple->getValues());
    }

    public function testGetValuesReturnsPostedArrayOfMultipleField(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['select' => ['b', 'a']]);

        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testGetValuesReturnsPostedStringOfMultipleField(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['select' => 'a']);

        $this->assertSame(['a'], $field->getValues());
    }

    public function testGetValuesReturnsUnknownOptionsBecauseValidationIsResponsible(): void
    {
        $field = $this->createField(multiple: true);

        $this->assertFalse($field->validate(inputData: ['select' => ['a', 'x']]));
        $this->assertSame(['a', 'x'], $field->getValues());
    }

    public function testGetValuesReturnsPreviousValueAfterRejectedArrayInputOfSingleField(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertFalse($field->validate(inputData: ['select' => ['b']]));
        $this->assertSame(['a'], $field->getValues());
    }

    public function testGetValuesOfSingleFieldConstructedWithArrayAcceptsArrayInput(): void
    {
        $field = $this->createField(initialValue: ['a']);
        $field->validate(inputData: ['select' => ['a', 'b']]);

        $this->assertSame(['a', 'b'], $field->getValues());
    }

    public function testGetValuesThrowsForNestedArrayAfterFailedValidation(): void
    {
        $field = $this->createField(multiple: true);

        $this->assertFalse($field->validate(inputData: ['select' => ['a', ['b']]]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field select');
        $this->expectExceptionMessage('array');

        $field->getValues();
    }

    /**
     * @return array<string, array{bool, null|string|array<mixed>}>
     */
    public static function validInputProvider(): array
    {
        return [
            'single option' => [false, 'a'],
            'empty string' => [false, ''],
            'missing key' => [false, null],
            'multiple: string' => [true, 'a'],
            'multiple: array' => [true, ['a', 'b']],
            'multiple: array with empty string' => [true, ['']],
            'multiple: empty array' => [true, []],
            'multiple: zero string is empty for validation' => [true, ['0']],
            'multiple: empty nested array is empty for validation' => [true, [[]]],
            'multiple: missing key' => [true, null],
        ];
    }

    /**
     * @param null|string|array<mixed> $input
     */
    #[DataProvider('validInputProvider')]
    public function testGetValuesNeverThrowsAfterSuccessfulValidation(bool $multiple, null|string|array $input): void
    {
        $field = $this->createField(multiple: $multiple);
        $inputData = $input === null ? [] : ['select' => $input];

        $this->assertTrue($field->validate(inputData: $inputData));
        // Must not throw: a validated field is always readable
        $field->getValues();
    }
}