<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\TextField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

/**
 * The typed string value of TextField (and of its base classes TextualField, InputField, StringInputField and
 * SettableStringInputField).
 */
final class TextFieldValueTest extends TestCase
{
    private function createField(?string $value = null): TextField
    {
        return new TextField(
            name: 'field',
            label: HtmlText::fromHtml(html: 'Label'),
            value: $value,
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('initial', $this->createField(value: 'initial')->getValueAsString());
    }

    public function testStringInputIsTrimmed(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['field' => '  text  ']));

        $this->assertSame('text', $field->getValueAsString());
    }

    public function testZeroWidthSpacesAreRemovedFromStringInput(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['field' => "a\u{200B}b\u{200B}"]));

        $this->assertSame('ab', $field->getValueAsString());
    }

    public function testConstructorValueIsNormalizedLikeInput(): void
    {
        $field = $this->createField(value: " a\u{200B} ");

        $this->assertSame('a', $field->getValueAsString());
    }

    public function testValueHasNotChangedRightAfterConstructionWithZeroWidthSpace(): void
    {
        $this->assertFalse($this->createField(value: "a\u{200B}")->valueHasChanged());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: 'initial');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));

        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputResetsValueAndAddsOneError(): void
    {
        $field = $this->createField(value: 'initial');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['field' => ['x']]));

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getValueAsString());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testNestedArrayInputResetsValue(): void
    {
        $field = $this->createField(value: 'initial');

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['field' => [['x']]])));
        $this->assertSame('', $field->getValueAsString());
    }

    public function testRejectedInputSkipsRulesSoThereIsNoSecondRequiredError(): void
    {
        $field = new TextField(
            name: 'field',
            label: HtmlText::fromHtml(html: 'Label'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
        );

        $field->validate(input: FormInput::fromArray(data: ['field' => ['x']]));

        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testRequiredErrorIsAddedForEmptyInput(): void
    {
        $field = new TextField(
            name: 'field',
            label: HtmlText::fromHtml(html: 'Label'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
        );

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['field' => '  '])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testRulesRunAgainAfterRejectedInputWasFollowedByValidInput(): void
    {
        $field = new TextField(
            name: 'field',
            label: HtmlText::fromHtml(html: 'Label'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
        );
        $field->validate(input: FormInput::fromArray(data: ['field' => ['x']]));

        $field->validate(input: FormInput::fromArray(data: ['field' => '']));

        $this->assertSame(2, $field->errorCollection->count());
    }

    public function testErrorTextComesFromTheFormMessages(): void
    {
        $field = $this->createField();
        $field->messages = FormMessages::german();

        $field->validate(input: FormInput::fromArray(data: ['field' => ['x']]));

        $this->assertSame(
            'Die ungültige Eingabe wurde ignoriert.',
            $field->errorCollection->getFirstError()->render(),
        );
    }

    public function testValueIsNotOverwrittenWhenValidatingTheCurrentValue(): void
    {
        $field = $this->createField(value: 'initial');

        $field->validateCurrentValue();

        $this->assertSame('initial', $field->getValueAsString());
    }

    public function testZeroIsNotEmpty(): void
    {
        $this->assertFalse($this->createField(value: '0')->isValueEmpty());
    }

    public function testBlankValueIsEmpty(): void
    {
        $this->assertTrue($this->createField(value: ' ')->isValueEmpty());
    }

    public function testSetValueChangesCurrentValueAndKeepsInitialValue(): void
    {
        $field = $this->createField(value: 'initial');

        $this->assertFalse($field->valueHasChanged());

        $field->setValue(value: 'other');

        $this->assertSame('other', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
        $this->assertFalse($field->hasErrors(withChildElements: false));
    }

    public function testSetValueBackToInitialValueIsNoChange(): void
    {
        $field = $this->createField(value: 'initial');
        $field->setValue(value: 'other');

        $field->setValue(value: ' initial ');

        $this->assertFalse($field->valueHasChanged());
    }

    public function testSetValueNormalizesLikeInput(): void
    {
        $field = $this->createField();

        $field->setValue(value: " a\u{200B} ");

        $this->assertSame('a', $field->getValueAsString());
    }

    public function testSetValueWorksAfterValidationAndKeepsInitialValue(): void
    {
        $field = $this->createField(value: 'initial');
        $field->validate(input: FormInput::fromArray(data: ['field' => 'posted']));

        $field->setValue(value: 'set');

        $this->assertSame('set', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testValueHasChangedAfterPostedDifferentValue(): void
    {
        $field = $this->createField(value: 'initial');

        $field->validate(input: FormInput::fromArray(data: ['field' => 'other']));

        $this->assertTrue($field->valueHasChanged());
    }

    public function testValueHasNotChangedAfterPostedSameValue(): void
    {
        $field = $this->createField(value: 'initial');

        $field->validate(input: FormInput::fromArray(data: ['field' => ' initial ']));

        $this->assertFalse($field->valueHasChanged());
    }

    public function testRenderValueEncodesValue(): void
    {
        $field = $this->createField(value: '<b>"x"</b>');

        $this->assertSame('&lt;b&gt;&quot;x&quot;&lt;/b&gt;', $field->renderValue());
    }
}
