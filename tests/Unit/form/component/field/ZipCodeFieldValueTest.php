<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\field\ZipCodeField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormNameRegistry;
use actra\yuf\html\HtmlText;
use Override;
use PHPUnit\Framework\TestCase;

final class ZipCodeFieldValueTest extends TestCase
{
    private static int $formCounter = 0;

    #[Override]
    protected function setUp(): void
    {
        FormNameRegistry::reset();
    }

    private function createField(?string $value = null, ?HtmlText $individualInvalidError = null): ZipCodeField
    {
        return new ZipCodeField(
            name: 'zip',
            label: HtmlText::fromHtml(html: 'Zip'),
            value: $value,
            individualInvalidError: $individualInvalidError,
        );
    }

    public function testValueIsEmptyStringAfterConstructionWithoutValue(): void
    {
        $this->assertSame('', $this->createField()->getValueAsString());
    }

    public function testValueIsStringAfterConstructionWithString(): void
    {
        $this->assertSame('8000', $this->createField(value: '8000')->getValueAsString());
    }

    public function testValidZipCodeIsStoredUnchanged(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => '8000']));

        $this->assertTrue($isValid);
        $this->assertSame('8000', $field->getValueAsString());
    }

    public function testSurroundingWhitespaceIsTrimmed(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => ' 8000 ']));

        $this->assertTrue($isValid);
        $this->assertSame('8000', $field->getValueAsString());
    }

    public function testInvalidZipCodeKeepsInputAndAddsTheDefaultMessage(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => 'x']));

        $this->assertFalse($isValid);
        $this->assertSame('x', $field->getValueAsString());
        $this->assertSame('The entered zip code is invalid.', $field->errorCollection->getFirstError()->render());
    }

    public function testInvalidZipCodeUsesTheGermanMessageOfTheForm(): void
    {
        $form = new Form(
            name: 'zipGermanForm' . ZipCodeFieldValueTest::$formCounter++,
            messages: FormMessages::german(),
        );
        $field = $this->createField();
        $form->addField(formField: $field);

        $field->validate(input: FormInput::fromArray(data: ['zip' => 'x']));

        $this->assertSame('Die eingegebene PLZ ist ungültig.', $field->errorCollection->getFirstError()->render());
    }

    public function testIndividualInvalidErrorWinsOverTheMessages(): void
    {
        $field = $this->createField(individualInvalidError: HtmlText::fromHtml(html: 'Own text'));

        $field->validate(input: FormInput::fromArray(data: ['zip' => 'x']));

        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Own text', $field->errorCollection->getFirstError()->render());
    }

    public function testRequiredErrorIsTheOnlyErrorOfAnEmptyField(): void
    {
        $field = new ZipCodeField(
            name: 'zip',
            label: HtmlText::fromHtml(html: 'Zip'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
        );

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => ' ']));

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('Required', $field->errorCollection->getFirstError()->render());
    }

    public function testEmptyZipCodeIsValidWithoutRequiredError(): void
    {
        $this->assertTrue($this->createField()->validate(input: FormInput::fromArray(data: ['zip' => ''])));
    }

    public function testCountryCodeFromInputDataIsUsedForValidation(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => '12345', 'countryCode' => 'DE']));

        $this->assertTrue($isValid);
        $this->assertSame('DE', $field->countryCode);
    }

    public function testCountryCodeFromInputIsAppliedBeforeTheValueIsChecked(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => '8000', 'countryCode' => 'DE']));

        $this->assertFalse($isValid);
    }

    public function testCountryCodeOfTheConstructorIsUsed(): void
    {
        $field = new ZipCodeField(name: 'zip', label: HtmlText::fromHtml(html: 'Zip'), countryCode: 'AT');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['zip' => '1010'])));
        $this->assertSame('AT', $field->countryCode);
    }

    public function testCountryCodeFieldNameIsConfigurable(): void
    {
        $field = new ZipCodeField(
            name: 'zip',
            label: HtmlText::fromHtml(html: 'Zip'),
            countryCodeFieldName: 'country',
        );

        $field->validate(input: FormInput::fromArray(data: ['zip' => '12345', 'country' => 'DE', 'countryCode' => 'AT']));

        $this->assertSame('DE', $field->countryCode);
    }

    public function testCountryWithoutFormatAcceptsAnyZipCode(): void
    {
        $field = $this->createField();

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: ['zip' => 'SW1A 1AA', 'countryCode' => 'GB'])));
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(value: '8000');

        $this->assertTrue($field->validate(input: FormInput::fromArray(data: [])));
        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(value: '8000');

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => ['x']]));

        $this->assertFalse($isValid);
        $this->assertSame(1, $field->errorCollection->count());
        $this->assertSame('', $field->getValueAsString());
    }

    public function testArrayAsCountryCodeInputIsIgnoredAndKeepsCountryCode(): void
    {
        $field = $this->createField();

        $isValid = $field->validate(input: FormInput::fromArray(data: ['zip' => '8000', 'countryCode' => ['x']]));

        $this->assertTrue($isValid);
        $this->assertSame('CH', $field->countryCode);
    }

    public function testSetValueChangesTheCurrentValueOnly(): void
    {
        $field = $this->createField(value: '8000');

        $field->setValue(value: ' 3000 ');

        $this->assertSame('3000', $field->getValueAsString());
        $this->assertTrue($field->valueHasChanged());
        $this->assertFalse($field->hasErrors(withChildElements: true));
    }

    public function testSetValueIsValidatedWithValidateCurrentValue(): void
    {
        $field = $this->createField();
        $field->setValue(value: 'x');

        $this->assertFalse($field->validateCurrentValue());
    }
}
