<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\FormInput;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PhoneNumberFieldValueTest extends TestCase
{
    private function createField(
        ?string $value = null,
        string $countryCode = 'CH',
        ?HtmlText $requiredErrorMessage = null,
    ): PhoneNumberField {
        return new PhoneNumberField(
            name: 'phone',
            label: HtmlText::fromHtml(html: 'Phone'),
            value: $value,
            invalidErrorMessage: HtmlText::fromHtml(html: 'Invalid'),
            requiredErrorMessage: $requiredErrorMessage,
            countryCode: $countryCode,
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testValidConstructorValueIsStoredInInternalFormat(): void
    {
        $this->assertSame('+41.446681800', $this->createField(value: '044 668 18 00')->getValueAsString());
    }

    public function testConstructorValueIsReadWithTheCountryCodeOfTheField(): void
    {
        $field = $this->createField(value: '030 123456', countryCode: 'DE');

        $this->assertSame('+49.30123456', $field->getValueAsString());
    }

    public function testInvalidConstructorValueStaysAsGivenTrimmed(): void
    {
        $this->assertSame('abc', $this->createField(value: ' abc ')->getValueAsString());
    }

    public function testValidPhoneNumberIsTrimmedAndStoredInInternalFormat(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['phone' => ' 044 668 18 00 ']));

        $this->assertTrue($isValid);
        $this->assertSame('+41.446681800', $field->getValueAsString());
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function equivalentNumberProvider(): iterable
    {
        yield 'national' => ['044 668 18 00', 'CH', '+41.446681800'];
        yield 'international with plus' => ['+41 44 668 18 00', 'CH', '+41.446681800'];
        yield 'international with 00' => ['0041446681800', 'CH', '+41.446681800'];
        yield 'internal format' => ['+41.446681800', 'CH', '+41.446681800'];
        yield 'mobile' => ['079 123 45 67', 'CH', '+41.791234567'];
        yield 'foreign number with other default country' => ['+41 44 668 18 00', 'DE', '+41.446681800'];
        yield 'German number' => ['030 123456', 'DE', '+49.30123456'];
    }

    #[DataProvider('equivalentNumberProvider')]
    public function testNumbersAreStoredInInternalFormat(string $input, string $countryCode, string $expected): void
    {
        $field = $this->createField(countryCode: $countryCode);

        $isValid = $field->validate(input: FormInput::fromArray(data: ['phone' => $input]));

        $this->assertTrue($isValid);
        $this->assertSame($expected, $field->getValueAsString());
    }

    public function testInvalidPhoneNumberKeepsInputAndAddsTheError(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['phone' => ' abc ']));

        $this->assertFalse($isValid);
        $this->assertSame('abc', $field->getValueAsString());
        $this->assertSame(['Invalid'], array_map(
            callback: fn(HtmlText $error): string => $error->render(),
            array: $field->errorCollection->listErrors(),
        ));
    }

    public function testNumberWithTooFewDigitsIsInvalid(): void
    {
        $field = $this->createField();

        $this->assertFalse($field->validate(input: FormInput::fromArray(data: ['phone' => '12'])));
    }

    public function testEmptyInputIsValidWithoutRequiredError(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['phone' => '  '])));
    }

    public function testEmptyInputGivesOnlyTheRequiredError(): void
    {
        $field = $this->createField(requiredErrorMessage: HtmlText::fromHtml(html: 'Required'));

        $isValid = $field->validate(input: FormInput::fromArray(data: ['phone' => '']));

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
        $this->assertTrue($field->isRequired());
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: '044 668 18 00');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(value: '044 668 18 00');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['phone' => ['x']]));

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayAsCountryCodeInputIsIgnoredAndKeepsCountryCode(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(
            input: FormInput::fromArray(data: ['phone' => '044 668 18 00', 'countryCode' => ['x']]),
        );

        $this->assertTrue($isValid);
        $this->assertSame('CH', $field->countryCode);
        $this->assertSame('+41.446681800', $field->getValueAsString());
    }

    public function testStringCountryCodeInputIsUsedForTheNumberOfTheSameRequest(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['phone' => '030 123456', 'countryCode' => 'DE']));

        $this->assertSame('DE', $field->countryCode);
        $this->assertSame('+49.30123456', $field->getValueAsString());
    }

    public function testCountryCodeFieldNameIsConfigurable(): void
    {
        $field = new PhoneNumberField(
            name: 'phone',
            label: HtmlText::fromHtml(html: 'Phone'),
            value: null,
            invalidErrorMessage: HtmlText::fromHtml(html: 'Invalid'),
            countryCodeFieldName: 'country',
        );

        $field->validate(
            input: FormInput::fromArray(data: ['phone' => '030 123456', 'country' => 'DE', 'countryCode' => 'FR']),
        );

        $this->assertSame('DE', $field->countryCode);
    }

    public function testSetValueNormalizesAndChangesTheCurrentValueOnly(): void
    {
        $field = $this->createField(value: '044 668 18 00');

        $field->setValue(value: '079 123 45 67');

        $this->assertSame('+41.791234567', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
    }

    public function testValueHasChangedIsFalseForTheSameNumberInAnotherFormat(): void
    {
        $field = $this->createField(value: '044 668 18 00');

        $field->validate(input: FormInput::fromArray(data: ['phone' => '+41 44 668 18 00']));

        $this->assertFalse($field->valueHasChanged());
    }

    public function testValueHasChangedIsTrueForAnotherNumber(): void
    {
        $field = $this->createField(value: '044 668 18 00');

        $field->validate(input: FormInput::fromArray(data: ['phone' => '079 123 45 67']));

        $this->assertTrue($field->valueHasChanged());
    }

    public function testRenderValueIsInternationalFormatByDefault(): void
    {
        $this->assertSame('+41 44 668 18 00', $this->createField(value: '044 668 18 00')->renderValue());
    }

    public function testRenderValueIsInternalFormatIfConfigured(): void
    {
        $field = new PhoneNumberField(
            name: 'phone',
            label: HtmlText::fromHtml(html: 'Phone'),
            value: '044 668 18 00',
            invalidErrorMessage: HtmlText::fromHtml(html: 'Invalid'),
            renderInternalFormat: true,
        );

        $this->assertSame('+41.446681800', $field->renderValue());
    }

    public function testRenderValueOfInvalidNumberIsEncodedWithAndWithoutError(): void
    {
        $withoutError = $this->createField(value: 'a"<b');
        $withError = $this->createField();
        $withError->validate(input: FormInput::fromArray(data: ['phone' => 'a"<b']));

        $this->assertSame('a&quot;&lt;b', $withoutError->renderValue());
        $this->assertSame('a&quot;&lt;b', $withError->renderValue());
    }

    public function testRenderValueOfEmptyFieldIsEmpty(): void
    {
        $this->assertSame('', $this->createField()->renderValue());
    }

    public function testInvalidInputIsEncodedWhenRenderedBack(): void
    {
        $field = $this->createField();

        $field->validate(input: FormInput::fromArray(data: ['phone' => '"><b>x']));

        $this->assertTrue($field->hasErrors(withChildElements: true));
        $this->assertSame('&quot;&gt;&lt;b&gt;x', $field->renderValue());
        $this->assertStringNotContainsString('"><b>', $field->getHtmlTag()->render());
    }
}
