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

    public function testArrayInputIsRejectedAndKeepsPreviousValueNormalizedByRule(): void
    {
        $field = $this->createField(value: '044 668 18 00');

        $isValid = $field->validate(inputData: ['phone' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertTrue($field->hasErrors(withChildElements: true));
        // The rules still run on the kept value and normalize it.
        $this->assertSame('+41.446681800', $field->getRawValue());
    }

    public function testArrayAsCountryCodeInputIsIgnoredAndKeepsCountryCode(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['phone' => '044 668 18 00', 'countryCode' => ['x']]);

        $this->assertTrue($isValid);
        $this->assertSame('CH', $field->countryCode);
        $this->assertSame('+41.446681800', $field->getRawValue());
    }

    public function testStringCountryCodeInputIsUsed(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['phone' => '030 123456', 'countryCode' => 'DE']);

        $this->assertSame('DE', $field->countryCode);
    }

    public function testInvalidInputIsEncodedWhenRenderedBack(): void
    {
        $field = $this->createField();

        $field->validate(inputData: ['phone' => '"><b>x']);

        $this->assertTrue($field->hasErrors(withChildElements: true));
        $this->assertSame('&quot;&gt;&lt;b&gt;x', $field->renderValue());
        $this->assertStringNotContainsString('"><b>', (string)$field->getHtmlTag()?->render());
    }
}