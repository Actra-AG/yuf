<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\component\layout\CheckboxOptionsLayout;
use actra\yuf\html\HtmlText;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BooleanFieldValueTest extends TestCase
{
    private function createField(bool $isCheckedByDefault = false, ?HtmlText $requiredError = null): BooleanField
    {
        return new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: $isCheckedByDefault,
            requiredError: $requiredError
        );
    }

    public function testIsNoCheckboxOptionsFieldAnymore(): void
    {
        $this->assertNotContains(CheckboxOptionsField::class, class_parents(object_or_class: BooleanField::class));
    }

    public function testIsCheckedAfterConstructionWithDefault(): void
    {
        $this->assertTrue($this->createField(isCheckedByDefault: true)->isChecked());
        $this->assertFalse($this->createField(isCheckedByDefault: false)->isChecked());
    }

    public function testListWithCheckedIsChecked(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['boolean' => ['checked']]);

        $this->assertTrue($isValid);
        $this->assertTrue($field->isChecked());
    }

    public function testTextCheckedIsChecked(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: ['boolean' => 'checked']));
        $this->assertTrue($field->isChecked());
    }

    public function testMissingKeyIsNotChecked(): void
    {
        $field = $this->createField(isCheckedByDefault: true);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertFalse($field->isChecked());
    }

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function invalidInputProvider(): array
    {
        return [
            'other text' => [['boolean' => 'yes']],
            'empty text' => [['boolean' => '']],
            'other list' => [['boolean' => ['yes']]],
            'empty list' => [['boolean' => []]],
            'list with two entries' => [['boolean' => ['checked', 'checked']]],
            'nested' => [['boolean' => [['checked']]]],
            'int' => [['boolean' => 1]],
        ];
    }

    /**
     * @param array<array-key, mixed> $inputData
     */
    #[DataProvider('invalidInputProvider')]
    public function testInvalidInputResetsTheValueWithOneError(array $inputData): void
    {
        $field = $this->createField(isCheckedByDefault: true, requiredError: HtmlText::encoded(textContent: 'R'));

        $isValid = $field->validate(inputData: $inputData);

        $this->assertFalse($isValid);
        $this->assertFalse($field->isChecked());
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('The invalid input was ignored.', $field->errorCollection->getFirstError()->render());
    }

    public function testRequiredRuleMeansMustBeChecked(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Accept the terms'));

        $this->assertFalse($field->validate(inputData: []));
        $this->assertSame('Accept the terms', $field->errorCollection->getFirstError()->render());
        $this->assertTrue($field->isRequired());
    }

    public function testRequiredRuleIsFulfilledWhenChecked(): void
    {
        $field = $this->createField(requiredError: HtmlText::encoded(textContent: 'Accept the terms'));

        $this->assertTrue($field->validate(inputData: ['boolean' => ['checked']]));
    }

    public function testValueIsEmptyWhenNotChecked(): void
    {
        $this->assertTrue($this->createField()->isValueEmpty());
        $this->assertFalse($this->createField(isCheckedByDefault: true)->isValueEmpty());
    }

    public function testSetCheckedChangesOnlyTheCurrentValue(): void
    {
        $field = $this->createField(isCheckedByDefault: false);

        $field->setChecked(checked: true);

        $this->assertTrue($field->isChecked());
        $this->assertTrue($field->valueHasChanged());
        $field->setChecked(checked: false);
        $this->assertFalse($field->valueHasChanged());
    }

    public function testValueHasChangedAfterInput(): void
    {
        $field = $this->createField(isCheckedByDefault: true);

        $field->validate(inputData: []);

        $this->assertTrue($field->valueHasChanged());
    }

    public function testRenderValue(): void
    {
        $this->assertSame('checked', $this->createField(isCheckedByDefault: true)->renderValue());
        $this->assertSame('', $this->createField()->renderValue());
    }

    public function testLegacySetterIsNotAvailable(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('setChecked()');

        $this->createField()->setValue(value: true);
    }

    public function testAllLayoutsAreAccepted(): void
    {
        foreach (CheckboxOptionsLayout::cases() as $layout) {
            $field = new BooleanField(
                name: 'boolean',
                label: HtmlText::encoded(textContent: 'Boolean'),
                isCheckedByDefault: false,
                layout: $layout
            );

            $this->assertFalse($field->isChecked());
        }
    }
}