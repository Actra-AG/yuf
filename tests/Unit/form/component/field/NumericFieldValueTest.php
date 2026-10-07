<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\IntegerField;
use actra\yuf\form\component\field\NumericField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * NumericField is a final IntegerField with its own renderer (`inputmode="numeric"`, `pattern`).
 */
final class NumericFieldValueTest extends TestCase
{
    private function createField(?int $initialValue = null): NumericField
    {
        return new NumericField(
            name: 'number',
            label: HtmlText::encoded(textContent: 'Number'),
            initialValue: $initialValue,
        );
    }

    public function testIsFinal(): void
    {
        $this->assertTrue(new ReflectionClass(objectOrClass: NumericField::class)->isFinal());
    }

    public function testInitialValueIsReturned(): void
    {
        $this->assertSame(7, $this->createField(initialValue: 7)->getValueAsInt());
    }

    public function testEmptyFieldHasNoValue(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: []));

        $this->assertNull($field->getValueAsInt());
    }

    public function testPostedValueIsParsedLikeAnInteger(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['number' => ' -42 '])));
        $this->assertSame(-42, $field->getValueAsInt());
    }

    public function testLeadingZerosAreNotKept(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['number' => '007'])));
        $this->assertSame(7, $field->getValueAsInt());
        $this->assertStringContainsString('value="7"', $field->render());
    }

    public function testDecimalsAreRejected(): void
    {
        $field = $this->createField(initialValue: 7);

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['number' => '1.5'])));
        $this->assertStringContainsString('value="1.5"', $field->render());
    }

    public function testMinAndMaxLengthAreKept(): void
    {
        $field = new NumericField(
            name: 'number',
            label: HtmlText::encoded(textContent: 'Number'),
            minLength: 2,
            maxLength: 4,
        );

        $this->assertSame(2, $field->minLength);
        $this->assertSame(4, $field->maxLength);
    }
}
