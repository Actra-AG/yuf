<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class ToggleFieldValueTest extends TestCase
{
    private function createField(?string $initialValue = null, ?HtmlText $requiredError = null): ToggleField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::fromHtml(html: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::fromHtml(html: 'B'));

        return new ToggleField(
            name: 'toggle',
            label: HtmlText::fromHtml(html: 'Toggle'),
            formOptions: $formOptions,
            initialValue: $initialValue,
            requiredError: $requiredError,
        );
    }

    public function testValueIsEmptyAfterConstructionWithoutValue(): void
    {
        $field = $this->createField();

        $this->assertSame('', $field->getValueAsString());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('b', $this->createField(initialValue: 'b')->getValueAsString());
    }

    public function testStringInputIsStored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['toggle' => 'a']));

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getValueAsString());
    }

    public function testMissingKeyGivesEmptyValueAndIsValid(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame('', $field->getValueAsString());
    }

    public function testPostedEmptyStringIsValidWithoutRequiredRule(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['toggle' => ''])));
        $this->assertSame('', $field->getValueAsString());
    }

    public function testRequiredRuleFailsForEmptyValue(): void
    {
        $field = $this->createField(requiredError: HtmlText::fromHtml(html: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testArrayInputResetsTheValueWithOneError(): void
    {
        $field = $this->createField(initialValue: 'b');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['toggle' => ['a']]));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testUnknownOptionIsInvalidAndResetsTheValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['toggle' => 'x']));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame('Selected invalid value in field toggle', $field->errorCollection->getFirstError()->render());
    }

    public function testIsSelected(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->isSelected(optionKey: 'a'));
        $this->assertFalse($field->isSelected(optionKey: 'b'));
        $this->assertFalse($field->isMultiple());
    }

    public function testSetValueChangesOnlyTheCurrentValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->setValue(value: 'b');

        $this->assertSame('b', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testRequiredFieldIsRequired(): void
    {
        $this->assertTrue($this->createField(requiredError: HtmlText::fromHtml(html: 'R'))->isRequired());
        $this->assertFalse($this->createField()->isRequired());
    }
}
