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
}