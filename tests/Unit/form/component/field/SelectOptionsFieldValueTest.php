<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use actra\yuf\tests\Double\form\FormContextFactory;
use PHPUnit\Framework\TestCase;

final class SelectOptionsFieldValueTest extends TestCase
{
    private function createField(?string $initialValue = null, ?HtmlText $requiredError = null): SelectOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::fromHtml(html: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::fromHtml(html: 'B'));

        return new SelectOptionsField(
            name: 'select',
            label: HtmlText::fromHtml(html: 'Select'),
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
        $this->assertSame('a', $this->createField(initialValue: 'a')->getValueAsString());
    }

    public function testValidOptionIsStored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['select' => 'a']));

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getValueAsString());
    }

    public function testEmptyStringIsAValidValueWithoutRequiredRule(): void
    {
        $field = $this->createField(initialValue: 'a');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['select' => '']));

        $this->assertTrue($isValid);
        $this->assertSame('', $field->getValueAsString());
    }

    public function testEmptyStringFailsTheRequiredRule(): void
    {
        $field = $this->createField(requiredError: HtmlText::fromHtml(html: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['select' => ''])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testUnknownOptionIsInvalidAndResetsTheValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['select' => 'x']));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Selected invalid value in field select', $field->errorCollection->getFirstError()->render());
    }

    public function testUnknownOptionGivesNoSecondRequiredError(): void
    {
        $field = $this->createField(requiredError: HtmlText::fromHtml(html: 'Required'));

        $field->validate(input: FormInput::fromArray(data: ['select' => 'x']));

        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testMissingKeyGivesEmptyValueAndIsValid(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputResetsTheValueWithOneError(): void
    {
        $field = $this->createField(initialValue: 'a');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['select' => ['b']]));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testSingleSelectDoesNotAcceptAnArrayAnymoreEvenIfConstructedWithValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['select' => ['a', 'b']])));
    }

    public function testIsSelected(): void
    {
        $field = $this->createField(initialValue: 'b');

        $this->assertTrue($field->isSelected(optionKey: 'b'));
        $this->assertFalse($field->isSelected(optionKey: 'a'));
        $this->assertFalse($field->isSelected(optionKey: ''));
        $this->assertTrue($this->createField()->isSelected(optionKey: ''));
    }

    public function testIsNotMultiple(): void
    {
        $this->assertFalse($this->createField()->isMultiple());
    }

    public function testSetValueChangesOnlyTheCurrentValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->setValue(value: 'b');

        $this->assertSame('b', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testEmptyValueLabelIsEmptyWithoutRequiredRule(): void
    {
        $this->assertSame('', $this->createField()->emptyValueLabel->render());
    }

    public function testEmptyValueLabelOfARequiredFieldComesFromTheMessagesOfTheForm(): void
    {
        $form = new Form(
            context: FormContextFactory::create(),
            name: 'selectEmptyLabelForm',
            messages: new FormMessages(selectEmptyOption: 'Choose'),
        );
        $field = $this->createField(requiredError: HtmlText::fromHtml(html: 'Required'));
        $this->assertSame('-- Please select --', $field->emptyValueLabel->render());

        $form->addField(formField: $field);

        $this->assertSame('Choose', $field->emptyValueLabel->render());
    }

    public function testIndividualEmptyValueLabelWins(): void
    {
        $field = new SelectOptionsField(
            name: 'select',
            label: HtmlText::fromHtml(html: 'Select'),
            formOptions: new FormOptions(),
            initialValue: null,
            individualEmptyValueLabel: HtmlText::fromHtml(html: 'None'),
        );

        $this->assertSame('None', $field->emptyValueLabel->render());
    }

    public function testChosenEnhancedFieldAddsTheCssClass(): void
    {
        $field = new SelectOptionsField(
            name: 'select',
            label: HtmlText::fromHtml(html: 'Select'),
            formOptions: new FormOptions(),
            initialValue: null,
            cssClasses: ['wide'],
            renderAsChosenEnhancedField: true,
        );

        $this->assertSame(['wide', 'chosen'], $field->cssClasses);
    }

    public function testDataAttributesAreStoredWithoutThePrefix(): void
    {
        $field = $this->createField();

        $field->addDataAttribute(name: 'data-foo', value: '1');
        $field->addDataAttribute(name: 'bar', value: '2');

        $this->assertSame(['foo' => '1', 'bar' => '2'], $field->getDataAttributes());
        $this->assertSame(['foo' => '1', 'bar' => '2'], $field->dataAttributes);
    }
}
