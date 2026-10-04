<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class CheckboxOptionsFieldValueTest extends TestCase
{
    /**
     * @param list<string> $initialValues
     */
    private function createField(array $initialValues = []): CheckboxOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new CheckboxOptionsField(
            name: 'checkbox',
            label: HtmlText::encoded(textContent: 'Checkbox'),
            formOptions: $formOptions,
            initialValues: $initialValues
        );
    }

    public function testValueIsArrayAfterConstruction(): void
    {
        $this->assertSame(['a'], $this->createField(initialValues: ['a'])->getRawValue());
    }

    public function testValueIsEmptyArrayAfterConstructionWithEmptyArray(): void
    {
        $this->assertSame([], $this->createField()->getRawValue());
    }

    public function testArrayInputIsStored(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => ['a', 'b']]);

        $this->assertTrue($isValid);
        $this->assertSame(['a', 'b'], $field->getRawValue());
    }

    /**
     * ODDITY: a posted string is accepted and stored as string (not wrapped into an array).
     */
    public function testStringInputIsStoredAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => 'a']);

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getRawValue());
    }

    public function testValueIsEmptyArrayAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(initialValues: ['a']);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame([], $field->getRawValue());
    }

    public function testUnknownOptionIsStoredButInvalid(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame(['x'], $field->getRawValue());
    }

    public function testNestedArrayIsStoredButInvalid(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['checkbox' => [['a']]]);

        $this->assertFalse($isValid);
        $this->assertSame([['a']], $field->getRawValue());
    }

    public function testBooleanFieldIsCheckedWithExactlyTheCheckedValue(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: false
        );

        $this->assertSame([], $field->getRawValue());
        $this->assertFalse($field->isChecked());

        $field->validate(inputData: ['boolean' => ['checked']]);

        $this->assertSame(['checked'], $field->getRawValue());
        $this->assertTrue($field->isChecked());
    }

    public function testBooleanFieldIsNotCheckedWhenStringIsPosted(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: true
        );

        $this->assertSame(['checked'], $field->getRawValue());

        $field->validate(inputData: ['boolean' => 'checked']);

        $this->assertSame('checked', $field->getRawValue());
        $this->assertFalse($field->isChecked());
    }

    public function testBooleanFieldValueIsEmptyArrayAfterValidationWithMissingKey(): void
    {
        $field = new BooleanField(
            name: 'boolean',
            label: HtmlText::encoded(textContent: 'Boolean'),
            isCheckedByDefault: true
        );

        $field->validate(inputData: []);

        $this->assertSame([], $field->getRawValue());
    }
}