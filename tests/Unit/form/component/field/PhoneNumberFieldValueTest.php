<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;
use TypeError;

final class PhoneNumberFieldValueTest extends TestCase
{
    private function createField(?string $value = null): PhoneNumberField
    {
        return new PhoneNumberField(
            name: 'phone',
            label: HtmlText::encoded(textContent: 'Phone'),
            value: $value,
            invalidErrorMessage: HtmlText::encoded(textContent: 'Invalid')
        );
    }

    public function testValueIsNullAfterConstructionWithoutValue(): void
    {
        $this->assertNull($this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithStringAndIsNotFormatted(): void
    {
        $this->assertSame('044 668 18 00', $this->createField(value: '044 668 18 00')->getRawValue());
    }

    public function testValidPhoneNumberIsTrimmedAndStoredInInternalFormat(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['phone' => ' 044 668 18 00 ']);

        $this->assertTrue($isValid);
        $this->assertSame('+41.446681800', $field->getRawValue());
    }

    public function testInvalidPhoneNumberKeepsInputAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['phone' => 'abc']);

        $this->assertFalse($isValid);
        $this->assertSame('abc', $field->getRawValue());
    }

    public function testValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: '044 668 18 00');

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    /**
     * KNOWN BUG (fixed in Task 2): validate() calls trim() on the input before the array check of setValue().
     * Other fields reject arrays with a validation error instead.
     */
    public function testArrayInputThrowsTypeErrorBecauseOfKnownBug(): void
    {
        $field = $this->createField();

        $this->expectException(TypeError::class);

        $field->validate(inputData: ['phone' => ['x']]);
    }

    /**
     * KNOWN BUG (fixed in Task 2): the country code input is assigned to a string property without a type check.
     */
    public function testArrayAsCountryCodeInputThrowsTypeErrorBecauseOfKnownBug(): void
    {
        $field = $this->createField();

        $this->expectException(TypeError::class);

        $field->validate(inputData: ['phone' => '044 668 18 00', 'countryCode' => ['x']]);
    }
}