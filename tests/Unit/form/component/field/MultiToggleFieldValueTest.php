<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\MultiToggleField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class MultiToggleFieldValueTest extends TestCase
{
    /**
     * @param list<string> $initialValues
     */
    private function createField(array $initialValues = [], ?HtmlText $requiredError = null): MultiToggleField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new MultiToggleField(
            name: 'toggle',
            label: HtmlText::encoded(textContent: 'Toggle'),
            formOptions: $formOptions,
            initialValues: $initialValues,
            requiredError: $requiredError,
        );
    }

    /**
     * Changed on purpose (v4): the value does not start with `[null]` but with `[]`.
     */
    public function testValuesAreEmptyAfterConstructionWithoutValues(): void
    {
        $field = $this->createField();

        $this->assertSame([], $field->getValues());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testValuesAfterConstruction(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(initialValues: ['a', 'b'])->getValues());
    }

    public function testListInputIsStored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['toggle' => ['b', 'a']]));

        $this->assertTrue($isValid);
        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testMissingKeyGivesEmptyList(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame([], $field->getValues());
    }

    /**
     * Changed on purpose (v4): v3 wrapped a posted string (`toggle=a`) into a list.
     */
    public function testScalarIsInvalidInputAndNotWrapped(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['toggle' => 'a'])));
        $this->assertSame([], $field->getValues());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testEmptyStringIsInvalidInputAndNotWrapped(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['toggle' => ''])));
        $this->assertSame([], $field->getValues());
    }

    public function testUnknownOptionIsInvalidAndResetsTheValues(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['toggle' => ['a', 'x']])));
        $this->assertSame([], $field->getValues());
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testRequiredRuleFailsForEmptyList(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testIsSelectedAndIsMultiple(): void
    {
        $field = $this->createField(initialValues: ['b']);

        $this->assertTrue($field->isSelected(optionKey: 'b'));
        $this->assertFalse($field->isSelected(optionKey: 'a'));
        $this->assertTrue($field->isMultiple());
    }

    public function testAddedAndRemovedValues(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $field->validate(input: FormInput::fromArray(data: ['toggle' => ['b']]));

        $this->assertTrue($field->valueHasChanged());
        $this->assertSame(['b'], $field->getAddedValues());
        $this->assertSame(['a'], $field->getRemovedValues());
    }
}
