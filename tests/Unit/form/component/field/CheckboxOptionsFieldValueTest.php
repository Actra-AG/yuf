<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CheckboxOptionsFieldValueTest extends TestCase
{
    /**
     * @param list<string> $initialValues
     */
    private function createField(array $initialValues = [], ?HtmlText $requiredError = null): CheckboxOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new CheckboxOptionsField(
            name: 'checkbox',
            label: HtmlText::encoded(textContent: 'Checkbox'),
            formOptions: $formOptions,
            initialValues: $initialValues,
            requiredError: $requiredError
        );
    }

    public function testGetValuesReturnsConstructorValues(): void
    {
        $this->assertSame(['a', 'b'], $this->createField(initialValues: ['a', 'b'])->getValues());
    }

    public function testGetValuesIsEmptyForEmptyConstructorArray(): void
    {
        $field = $this->createField();

        $this->assertSame([], $field->getValues());
        $this->assertTrue($field->isValueEmpty());
    }

    public function testArrayInputIsStored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['checkbox' => ['a', 'b']]));

        $this->assertTrue($isValid);
        $this->assertSame(['a', 'b'], $field->getValues());
    }

    public function testGetValuesReindexesArrayAndKeepsOrder(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['checkbox' => [4 => 'b', 1 => 'a']]));

        $this->assertSame(['b', 'a'], $field->getValues());
    }

    public function testGetValuesIsEmptyAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame([], $field->getValues());
    }

    /**
     * Changed on purpose (v4): a scalar posted to a multi field is invalid input, it is not wrapped.
     */
    public function testStringInputIsInvalidInput(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $isValid = $field->validate(input: FormInput::fromArray(data: ['checkbox' => 'a']));

        $this->assertFalse($isValid);
        $this->assertSame([], $field->getValues());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testUnknownOptionIsInvalidAndResetsTheValues(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['checkbox' => ['a', 'x']]));

        $this->assertFalse($isValid);
        $this->assertSame([], $field->getValues());
        $this->assertSame(1, $field->errorCollection->count());
    }

    public function testNestedArrayIsInvalidInputAndGetValuesDoesNotThrow(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['checkbox' => ['a', ['b']]])));
        $this->assertSame([], $field->getValues());
    }

    public function testRequiredRuleFailsForEmptyValues(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Required'));

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
        $this->assertTrue($field->isRequired());
    }

    public function testSetValuesChangesOnlyTheCurrentValues(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $field->setValues(values: ['a', 'b']);

        $this->assertSame(['a', 'b'], $field->getValues());
        $this->assertTrue($field->valueHasChanged());
        $this->assertSame(['b'], $field->getAddedValues());
        $this->assertSame([], $field->getRemovedValues());
    }

    public function testValueHasChangedIsFalseWithoutChange(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertFalse($field->valueHasChanged());
        $field->validate(input: FormInput::fromArray(data: ['checkbox' => ['a']]));
        $this->assertFalse($field->valueHasChanged());
    }

    /**
     * @return array<string, array{null|string|array<mixed>, bool}>
     */
    public static function inputProvider(): array
    {
        return [
            'array' => [['a', 'b'], true],
            'array with empty string' => [[''], true],
            'empty array' => [[], true],
            'zero string is an unknown option' => [['0'], false],
            'string' => ['a', false],
            'empty string' => ['', false],
            'empty nested array' => [[[]], false],
            'missing key' => [null, true],
        ];
    }

    /**
     * @param null|string|array<mixed> $input
     */
    #[DataProvider('inputProvider')]
    public function testValidationResultAndGetValuesNeverThrows(null|string|array $input, bool $expectedValid): void
    {
        $field = $this->createField();
        $inputData = $input === null ? [] : ['checkbox' => $input];

        $this->assertSame($expectedValid, $field->validate(input: FormInput::fromArray(data: $inputData)));
        // Must not throw: a field is always readable
        $field->getValues();
    }

    public function testIsSelected(): void
    {
        $field = $this->createField(initialValues: ['b']);

        $this->assertTrue($field->isSelected(optionKey: 'b'));
        $this->assertFalse($field->isSelected(optionKey: 'a'));
    }
}