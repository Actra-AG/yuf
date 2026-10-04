<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\InitialValueBooleanField;
use actra\yuf\tests\Double\form\InitialValueCheckboxOptionsField;
use actra\yuf\tests\Double\form\InitialValueRadioOptionsField;
use LogicException;
use PHPUnit\Framework\TestCase;
use TypeError;

/**
 * The protected `setInitialValue()`, `setInitialValues()` and `setInitiallyChecked()` of the option fields: they set
 * the current and the initial value, but only before the field has been validated.
 */
final class OptionsInitialValueTest extends TestCase
{
    private function createOptions(): FormOptions
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return $formOptions;
    }

    private function createRadio(): InitialValueRadioOptionsField
    {
        return new InitialValueRadioOptionsField(
            name: 'radio',
            label: HtmlText::encoded(textContent: 'Radio'),
            formOptions: $this->createOptions(),
            initialValue: null
        );
    }

    private function createCheckbox(): InitialValueCheckboxOptionsField
    {
        return new InitialValueCheckboxOptionsField(
            name: 'checkbox',
            label: HtmlText::encoded(textContent: 'Checkbox'),
            formOptions: $this->createOptions(),
            initialValues: []
        );
    }

    private function createBoolean(): InitialValueBooleanField
    {
        return new InitialValueBooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: false
        );
    }

    public function testSingleSetInitialValueSetsCurrentAndInitialValue(): void
    {
        $field = $this->createRadio();

        $field->fill(value: 'b');

        $this->assertSame('b', $field->getValueAsString());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testSingleSetInitialValueNullSelectsNothing(): void
    {
        $field = $this->createRadio();
        $field->fill(value: 'b');

        $field->fill(value: null);

        $this->assertSame('', $field->getValueAsString());
    }

    public function testSingleLastCallWinsBeforeValidation(): void
    {
        $field = $this->createRadio();

        $field->fill(value: 'a');
        $field->fill(value: 'b');

        $this->assertSame('b', $field->getValueAsString());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testSingleSetInitialValueAfterValidateThrows(): void
    {
        $field = $this->createRadio();
        $field->validate(input: FormInput::fromArray(data: []));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('field radio');

        $field->fill(value: 'a');
    }

    public function testSingleSetInitialValueAfterValidateCurrentValueThrows(): void
    {
        $field = $this->createRadio();
        $field->validateCurrentValue();

        $this->expectException(LogicException::class);

        $field->fill(value: 'a');
    }

    public function testSinglePublicSetterStillWorksAfterValidate(): void
    {
        $field = $this->createRadio();
        $field->fill(value: 'a');
        $field->validate(input: FormInput::fromArray(data: ['radio' => 'b']));

        $field->setValue(value: 'a');

        $this->assertSame('a', $field->getValueAsString());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testMultiSetInitialValuesSetsCurrentAndInitialValues(): void
    {
        $field = $this->createCheckbox();

        $field->fill(values: ['a', 'b']);

        $this->assertSame(['a', 'b'], $field->getValues());
        $this->assertFalse($field->valueHasChanged());
        $this->assertSame([], $field->getAddedValues());
    }

    public function testMultiSetInitialValuesAfterValidateThrows(): void
    {
        $field = $this->createCheckbox();
        $field->validate(input: FormInput::fromArray(data: []));

        $this->expectException(LogicException::class);

        $field->fill(values: ['a']);
    }

    public function testMultiPublicSetterKeepsTheInitialValues(): void
    {
        $field = $this->createCheckbox();
        $field->fill(values: ['a']);
        $field->validate(input: FormInput::fromArray(data: ['checkbox' => ['a']]));

        $field->setValues(values: ['b']);

        $this->assertTrue($field->valueHasChanged());
        $this->assertSame(['b'], $field->getAddedValues());
        $this->assertSame(['a'], $field->getRemovedValues());
    }

    public function testMultiSetInitialValuesWithNonStringThrowsTypeError(): void
    {
        $field = $this->createCheckbox();

        $this->expectException(TypeError::class);

        // @phpstan-ignore argument.type (deliberately wrong entry type, a TypeError is expected)
        $field->fill(values: [1]);
    }

    public function testBooleanSetInitiallyCheckedSetsCurrentAndInitialValue(): void
    {
        $field = $this->createBoolean();

        $field->fill(checked: true);

        $this->assertTrue($field->isChecked());
        $this->assertFalse($field->valueHasChanged());
    }

    public function testBooleanSetInitiallyCheckedAfterValidateThrows(): void
    {
        $field = $this->createBoolean();
        $field->validate(input: FormInput::fromArray(data: []));

        $this->expectException(LogicException::class);

        $field->fill(checked: true);
    }

    public function testBooleanPublicSetterKeepsTheInitialValue(): void
    {
        $field = $this->createBoolean();
        $field->fill(checked: true);
        $field->validate(input: FormInput::fromArray(data: ['boolean' => ['checked']]));

        $field->setChecked(checked: false);

        $this->assertTrue($field->valueHasChanged());
    }
}