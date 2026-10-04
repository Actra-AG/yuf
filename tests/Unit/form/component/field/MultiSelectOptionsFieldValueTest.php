<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use LogicException;
use PHPUnit\Framework\TestCase;
use TypeError;

final class MultiSelectOptionsFieldValueTest extends TestCase
{
    /**
     * @param list<string> $initialValues
     */
    private function createField(array $initialValues = [], ?HtmlText $requiredError = null): MultiSelectOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));
        $formOptions->addItem(key: '0', htmlText: HtmlText::encoded(textContent: 'Zero'));

        return new MultiSelectOptionsField(
            name: 'select',
            label: HtmlText::encoded(textContent: 'Select'),
            formOptions: $formOptions,
            initialValues: $initialValues,
            requiredError: $requiredError
        );
    }

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

    public function testListInputIsStoredInOrder(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['select' => ['b', 'a']]);

        $this->assertTrue($isValid);
        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testKeysOfThePostedArrayAreDropped(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['select' => [7 => 'b', 2 => 'a']]);

        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testEmptyKeysAreDroppedAtInput(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: ['select' => ['', 'a']]));
        $this->assertSame(['a'], $field->getValues());
    }

    public function testZeroIsARealOptionKey(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: ['select' => ['0']]));
        $this->assertSame(['0'], $field->getValues());
        $this->assertFalse($field->isValueEmpty());
    }

    public function testMissingKeyGivesEmptyList(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame([], $field->getValues());
    }

    public function testEmptyArrayGivesEmptyList(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertTrue($field->validate(inputData: ['select' => []]));
        $this->assertSame([], $field->getValues());
    }

    public function testUnknownOptionResetsTheValuesWithOneError(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['select' => ['a', 'x']]);

        $this->assertFalse($isValid);
        $this->assertSame([], $field->getValues());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Selected invalid value in field select', $field->errorCollection->getFirstError()->render());
    }

    public function testScalarPostedToAMultiFieldIsInvalidInput(): void
    {
        $field = $this->createField(initialValues: ['b']);

        $isValid = $field->validate(inputData: ['select' => 'a']);

        $this->assertFalse($isValid);
        $this->assertSame([], $field->getValues());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testEmptyStringPostedToAMultiFieldIsInvalidInput(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(inputData: ['select' => '']));
    }

    public function testNestedArrayIsInvalidInput(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(inputData: ['select' => [['a']]]));
        $this->assertSame([], $field->getValues());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testRequiredRuleFailsForEmptyList(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $this->assertFalse($field->validate(inputData: []));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testRequiredRuleDoesNotRunForRejectedInput(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $field->validate(inputData: ['select' => 'a']);

        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testIsSelected(): void
    {
        $field = $this->createField(initialValues: ['a', '0']);

        $this->assertTrue($field->isSelected(optionKey: 'a'));
        $this->assertTrue($field->isSelected(optionKey: '0'));
        $this->assertFalse($field->isSelected(optionKey: 'b'));
        $this->assertFalse($field->isSelected(optionKey: ''));
    }

    public function testIsMultiple(): void
    {
        $this->assertTrue($this->createField()->isMultiple());
    }

    public function testSetValuesChangesOnlyTheCurrentValues(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $field->setValues(values: ['b']);

        $this->assertSame(['b'], $field->getValues());
        $this->assertTrue($field->valueHasChanged());
        $this->assertSame(['b'], $field->getAddedValues());
        $this->assertSame(['a'], $field->getRemovedValues());
    }

    public function testSetValuesDropsEmptyKeysAndReindexes(): void
    {
        $field = $this->createField();

        // @phpstan-ignore argument.type (a PHP array with keys: the setter must reindex it)
        $field->setValues(values: [5 => 'a', 9 => '', 3 => 'b']);

        $this->assertSame(['a', 'b'], $field->getValues());
    }

    public function testSetValuesWithANonStringEntryThrowsTypeError(): void
    {
        $field = $this->createField();

        $this->expectException(TypeError::class);

        // @phpstan-ignore argument.type (deliberately wrong entry type, a TypeError is expected)
        $field->setValues(values: ['a', 1]);
    }

    public function testSetValueIsNotAvailableOnAMultiField(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('setValues()');

        $this->createField()->setValue(value: ['a']);
    }

    public function testValueHasChangedIgnoresTheOrder(): void
    {
        $field = $this->createField(initialValues: ['a', 'b']);

        $field->validate(inputData: ['select' => ['b', 'a']]);

        $this->assertFalse($field->valueHasChanged());
        $this->assertSame([], $field->getAddedValues());
        $this->assertSame([], $field->getRemovedValues());
    }

    public function testAddedAndRemovedValuesAfterInput(): void
    {
        $field = $this->createField(initialValues: ['a', 'b']);

        $field->validate(inputData: ['select' => ['b', '0']]);

        $this->assertTrue($field->valueHasChanged());
        $this->assertSame(['0'], $field->getAddedValues());
        $this->assertSame(['a'], $field->getRemovedValues());
    }

    public function testEmptyValueLabelIsEmptyWithoutRequiredRule(): void
    {
        $this->assertSame('', $this->createField()->emptyValueLabel->render());
    }

    public function testChosenEnhancedFieldAddsTheCssClass(): void
    {
        $field = new MultiSelectOptionsField(
            name: 'select',
            label: HtmlText::encoded(textContent: 'Select'),
            formOptions: new FormOptions(),
            initialValues: [],
            renderAsChosenEnhancedField: true
        );

        $this->assertSame(['chosen'], $field->cssClasses);
    }
}