<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\RadioOptionsField;
use actra\yuf\form\FormOptions;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class RadioOptionsFieldValueTest extends TestCase
{
    private function createField(?string $initialValue = null): RadioOptionsField
    {
        $formOptions = new FormOptions();
        $formOptions->addItem(key: 'a', htmlText: HtmlText::encoded(textContent: 'A'));
        $formOptions->addItem(key: 'b', htmlText: HtmlText::encoded(textContent: 'B'));

        return new RadioOptionsField(
            name: 'radio',
            label: HtmlText::encoded(textContent: 'Radio'),
            formOptions: $formOptions,
            initialValue: $initialValue
        );
    }

    public function testValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('b', $this->createField(initialValue: 'b')->getRawValue());
    }

    public function testValidOptionIsStoredAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['radio' => 'a']);

        $this->assertTrue($isValid);
        $this->assertSame('a', $field->getRawValue());
    }

    public function testUnknownOptionIsStoredButInvalid(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['radio' => 'x']);

        $this->assertFalse($isValid);
        $this->assertSame('x', $field->getRawValue());
    }

    public function testValueIsNullAfterValidationWithMissingKeyAndFieldIsInvalid(): void
    {
        $field = $this->createField(initialValue: 'b');

        $isValid = $field->validate(inputData: []);

        $this->assertFalse($isValid);
        $this->assertNull($field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsPreviousValue(): void
    {
        $field = $this->createField(initialValue: 'b');

        $isValid = $field->validate(inputData: ['radio' => ['a']]);

        $this->assertFalse($isValid);
        $this->assertSame('b', $field->getRawValue());
    }
}