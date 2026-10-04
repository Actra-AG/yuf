<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\ZipCodeField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\TestCase;

final class ZipCodeFieldValueTest extends TestCase
{
    private function createField(): ZipCodeField
    {
        return new ZipCodeField(
            name: 'zip',
            label: HtmlText::encoded(textContent: 'Zip')
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getRawValue());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $field = new ZipCodeField(
            name: 'zip',
            label: HtmlText::encoded(textContent: 'Zip'),
            value: '8000'
        );

        $this->assertSame('8000', $field->getRawValue());
    }

    public function testValidZipCodeIsStoredUnchanged(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['zip' => '8000']);

        $this->assertTrue($isValid);
        $this->assertSame('8000', $field->getRawValue());
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['zip' => ' 8000 ']);

        $this->assertTrue($isValid);
        $this->assertSame('8000', $field->getRawValue());
    }

    public function testInvalidZipCodeKeepsInputAsString(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['zip' => 'x']);

        $this->assertFalse($isValid);
        $this->assertSame('x', $field->getRawValue());
    }

    public function testCountryCodeFromInputDataIsUsedForValidation(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['zip' => '12345', 'countryCode' => 'DE']);

        $this->assertTrue($isValid);
        $this->assertSame('DE', $field->countryCode);
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame('', $field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsPreviousValue(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['zip' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getRawValue());
    }

    public function testArrayAsCountryCodeInputIsIgnoredAndKeepsCountryCode(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(inputData: ['zip' => '8000', 'countryCode' => ['x']]);

        $this->assertTrue($isValid);
        $this->assertSame('CH', $field->countryCode);
    }
}