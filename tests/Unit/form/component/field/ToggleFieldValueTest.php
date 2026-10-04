<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

final class ToggleFieldValueTest extends TestCase
{
    /**
     * @param null|string|list<string> $initialValue
     */
    private function createField(null|string|array $initialValue = null, bool $multiple = false): ToggleField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new ToggleField(
            name: 'toggle',
            label: HtmlText::encoded(textContent: 'Toggle'),
            formOptions: $formOptions,
            initialValue: $initialValue,
            multiple: $multiple
        );
    }

    public function testSingleFieldValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
    }

    public function testSingleFieldValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('b', $this->createField(initialValue: 'b')->getRawValue());
    }

    public function testSingleFieldValueIsArrayAfterConstructionWithArray(): void
    {
        $this->assertSame(['a'], $this->createField(initialValue: ['a'])->getRawValue());
    }

    public function testSingleFieldStoresStringInput(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['toggle' => 'a']);

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getRawValue());
    }

    public function testSingleFieldValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    public function testSingleFieldRejectsArrayInputAndKeepsPreviousValue(): void
    {
        $field = $this->createField(initialValue: 'b');

        $isValid = $field->validate(inputData: ['toggle' => ['a']]);

        $this->assertFalse($isValid);
        $this->assertSame('b', $field->getRawValue());
    }

    public function testSingleFieldStoresUnknownOptionButIsInvalid(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['toggle' => 'x']);

        $this->assertFalse($isValid);
        $this->assertSame('x', $field->getRawValue());
    }

    /**
     * ODDITY: a multiple field without initial value does not start with an empty array but with [null].
     */
    public function testMultipleFieldValueIsArrayWithNullAfterConstructionWithoutValue(): void
    {
        $this->assertSame([null], $this->createField(multiple: true)->getRawValue());
    }

    public function testMultipleFieldWrapsStringInitialValueIntoArray(): void
    {
        $this->assertSame(['a'], $this->createField(initialValue: 'a', multiple: true)->getRawValue());
    }

    public function testMultipleFieldKeepsArrayInitialValue(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(initialValue: ['a', 'b'], multiple: true)->getRawValue());
    }

    public function testMultipleFieldStoresArrayInput(): void
    {
        $field = $this->createField(multiple: true);

        $isValid = $field->validate(inputData: ['toggle' => ['a', 'b']]);

        $this->assertTrue($isValid);
        $this->assertSame(['a', 'b'], $field->getRawValue());
    }

    public function testMultipleFieldWrapsStringInputIntoArray(): void
    {
        $field = $this->createField(multiple: true);

        $isValid = $field->validate(inputData: ['toggle' => 'a']);

        $this->assertTrue($isValid);
        $this->assertSame(['a'], $field->getRawValue());
    }

    public function testMultipleFieldWrapsEmptyStringIntoArray(): void
    {
        $field = $this->createField(multiple: true);

        $isValid = $field->validate(inputData: ['toggle' => '']);

        $this->assertTrue($isValid);
        $this->assertSame([''], $field->getRawValue());
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

        $isValid = $field->validate(inputData: ['toggle' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame(['x'], $field->getRawValue());
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

        $field->validate(inputData: ['toggle' => 'b']);

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

        $field->validate(inputData: ['toggle' => ['b']]);

        $this->assertSame('a', $field->getValueAsString());
    }

    public function testGetValueAsStringThrowsForArrayConstructorValue(): void
    {
        $field = $this->createField(initialValue: ['a']);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field toggle');
        $this->expectExceptionMessage('array');

        $field->getValueAsString();
    }

    public function testGetValueAsStringThrowsForMultipleFieldWithPostedArray(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['toggle' => ['a', 'b']]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field toggle');

        $field->getValueAsString();
    }

    public function testGetValueAsStringThrowsForMultipleFieldWithMissingKey(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: []);

        $this->expectException(UnexpectedValueException::class);

        $field->getValueAsString();
    }

    public function testGetValuesIsEmptyForNullOfSingleField(): void
    {
        $this->assertSame([], $this->createField()->getValues());
    }

    public function testGetValuesIsEmptyForNullOfMultipleField(): void
    {
        // The stored value is [null] (see testMultipleFieldValueIsArrayWithNullAfterConstructionWithoutValue)
        $this->assertSame([], $this->createField(multiple: true)->getValues());
    }

    public function testGetValuesReturnsListWithStringInitialValue(): void
    {
        $this->assertSame(['a'], $this->createField(initialValue: 'a')->getValues());
        $this->assertSame(['a'], $this->createField(initialValue: 'a', multiple: true)->getValues());
    }

    public function testGetValuesReturnsArrayInitialValue(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(initialValue: ['a', 'b'], multiple: true)->getValues());
    }

    public function testGetValuesConvertsIntEntriesToString(): void
    {
        $field = $this->createField(multiple: true);
        $field->setValue(value: [1, 'a']);

        $this->assertSame(['1', 'a'], $field->getValues());
    }

    public function testGetValuesReindexesArrayAndKeepsOrder(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['toggle' => [7 => 'b', 2 => 'a']]);

        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testGetValuesIsEmptyForPostedEmptyStringOfMultipleField(): void
    {
        $field = $this->createField(multiple: true);

        $this->assertTrue($field->validate(inputData: ['toggle' => '']));
        $this->assertSame([''], $field->getRawValue());
        $this->assertSame([], $field->getValues());
    }

    public function testGetValuesIsEmptyForPostedEmptyStringOfSingleField(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->validate(inputData: ['toggle' => '']));
        $this->assertSame([], $field->getValues());
    }

    public function testGetValuesWrapsPostedStringOfMultipleField(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['toggle' => 'b']);

        $this->assertSame(['b'], $field->getValues());
    }

    public function testGetValuesReturnsPostedArray(): void
    {
        $field = $this->createField(multiple: true);
        $field->validate(inputData: ['toggle' => ['b', 'a']]);

        $this->assertSame(['b', 'a'], $field->getValues());
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

    public function testGetValuesReturnsUnknownOptionsBecauseValidationIsResponsible(): void
    {
        $field = $this->createField(multiple: true);

        $this->assertFalse($field->validate(inputData: ['toggle' => ['a', 'x']]));
        $this->assertSame(['a', 'x'], $field->getValues());
    }

    public function testGetValuesReturnsPreviousValueAfterRejectedArrayInputOfSingleField(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertFalse($field->validate(inputData: ['toggle' => ['b']]));
        $this->assertSame(['a'], $field->getValues());
    }

    public function testGetValuesThrowsForNestedArrayAfterFailedValidation(): void
    {
        $field = $this->createField(multiple: true);

        $this->assertFalse($field->validate(inputData: ['toggle' => ['a', ['b']]]));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field toggle');
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
            'single: empty string' => [false, ''],
            'single: missing key' => [false, null],
            'multiple: string' => [true, 'a'],
            'multiple: empty string' => [true, ''],
            'multiple: array' => [true, ['a', 'b']],
            'multiple: array with empty string' => [true, ['']],
            'multiple: empty array' => [true, []],
            'multiple: zero string is empty for validation' => [true, ['0']],
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
        $inputData = $input === null ? [] : ['toggle' => $input];

        $this->assertTrue($field->validate(inputData: $inputData));
        // Must not throw: a validated field is always readable
        $field->getValues();
    }
}