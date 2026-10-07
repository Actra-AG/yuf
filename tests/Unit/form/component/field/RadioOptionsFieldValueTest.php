<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\RadioOptionsField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class RadioOptionsFieldValueTest extends TestCase
{
    private function createField(?string $initialValue = null, ?HtmlText $requiredError = null): RadioOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new RadioOptionsField(
            name: 'radio',
            label: HtmlText::encoded(textContent: 'Radio'),
            formOptions: $formOptions,
            initialValue: $initialValue,
            requiredError: $requiredError,
        );
    }

    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    public function testValueIsEmptyAfterConstructionWithoutValue(): void
    {
        $field = $this->createField();

        $this->assertSame('', $field->getValueAsString());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $field = $this->createField(initialValue: 'b');

        $this->assertSame('b', $field->getValueAsString());
        $this->assertFalse($field->isValueEmpty());
    }

    public function testValidOptionIsStored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['radio' => 'a']));

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getValueAsString());
    }

    public function testUnknownOptionIsInvalidAndResetsTheValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['radio' => 'x']));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
    }

    public function testUnknownOptionAddsExactlyOneErrorWithTheFieldName(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['radio' => 'x']));

        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Selected invalid value in field radio', $field->errorCollection->getFirstError()->render());
    }

    public function testMissingKeyIsInvalidBecauseARadioFieldIsAlwaysRequired(): void
    {
        $field = $this->createField(initialValue: 'b');

        $isValid = $field->validate(input: FormInput::fromArray(data: []));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame('Please select one of the options.', $field->errorCollection->getFirstError()->render());
    }

    public function testPostedEmptyStringIsEmptyAndInvalidBecauseOfTheRequiredRule(): void
    {
        $field = $this->createField(initialValue: 'b');

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['radio' => ''])));
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testArrayInputResetsTheValueWithOneErrorAndNoRequiredError(): void
    {
        $field = $this->createField(initialValue: 'b');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['radio' => ['a']]));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testNestedArrayInputIsInvalidInput(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['radio' => [['a']]])));
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('', $field->getValueAsString());
    }

    public function testDefaultRequiredTextIsTakenFromTheMessagesOfTheForm(): void
    {
        $form = new Form(name: 'radioGermanForm', messages: FormMessages::german());
        $field = $this->createField();
        $form->addField(formField: $field);

        $field->validate(input: FormInput::fromArray(data: []));

        $this->assertSame(
            'Bitte wählen Sie eine der Optionen aus.',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testIndividualRequiredErrorWins(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Choose!'));

        $field->validate(input: FormInput::fromArray(data: []));

        $this->assertSame('Choose!', $field->errorCollection->getFirstError()->render());
    }

    public function testFieldIsRequired(): void
    {
        $this->assertTrue($this->createField()->isRequired());
    }

    public function testUnknownOptionMessageUsesTheMessagesOfTheForm(): void
    {
        $form = new Form(
            name: 'radioCustomForm',
            messages: new FormMessages(invalidOption: 'Bad option in [field]!'),
        );
        $field = $this->createField();
        $form->addField(formField: $field);

        $field->validate(input: FormInput::fromArray(data: ['radio' => 'x']));

        $this->assertSame('Bad option in radio!', $field->errorCollection->getFirstError()->render());
    }

    public function testIsSelectedComparesTheExactKey(): void
    {
        $field = $this->createField(initialValue: 'a');

        $this->assertTrue($field->isSelected(optionKey: 'a'));
        $this->assertFalse($field->isSelected(optionKey: 'b'));
        $this->assertFalse($field->isSelected(optionKey: 'A'));
        $this->assertFalse($field->isSelected(optionKey: ''));
    }

    public function testSetValueChangesOnlyTheCurrentValue(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->setValue(value: 'b');

        $this->assertSame('b', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
        $field->setValue(value: 'a');
        $this->assertFalse($field->valueHasChanged());
    }

    public function testSetValueNullSelectsNothing(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->setValue(value: null);

        $this->assertSame('', $field->getValueAsString());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testSetValueDoesNotCheckAgainstTheOptions(): void
    {
        $field = $this->createField();

        $field->setValue(value: 'from database');

        $this->assertSame('from database', $field->getValueAsString());
    }

    public function testValueHasChangedAfterPostingAnotherOption(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->validate(input: FormInput::fromArray(data: ['radio' => 'b']));

        $this->assertTrue($field->valueHasChanged());
    }

    public function testValueHasNotChangedAfterPostingTheInitialOption(): void
    {
        $field = $this->createField(initialValue: 'a');

        $field->validate(input: FormInput::fromArray(data: ['radio' => 'a']));

        $this->assertFalse($field->valueHasChanged());
    }

    public function testRenderValueIsTheEncodedKey(): void
    {
        $this->assertSame('a', $this->createField(initialValue: 'a')->renderValue());
    }
}
