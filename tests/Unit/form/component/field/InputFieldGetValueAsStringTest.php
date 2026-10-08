<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\IbanNumberField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\StringInputField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\ZipCodeField;
use actra\yuf\form\FormInput;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `getValueAsString()` of the StringInputField subclasses that are not covered by their own value test
 * (HiddenField has its own value test).
 */
final class InputFieldGetValueAsStringTest extends TestCase
{
    /**
     * @return iterable<string, array{StringInputField, string}>
     */
    public static function fieldProvider(): iterable
    {
        $label = HtmlText::fromHtml(html: 'Label');
        $error = HtmlText::fromHtml(html: 'Invalid');

        yield 'text' => [new TextField(name: 'field', label: $label), 'field'];
        yield 'email' => [
            new EmailField(name: 'field', label: $label, value: null, invalidError: $error, dnsCheck: false),
            'field',
        ];
        yield 'zip code' => [new ZipCodeField(name: 'field', label: $label), 'field'];
        yield 'iban' => [new IbanNumberField(name: 'field', label: $label, value: null, invalidError: $error), 'field'];
        yield 'password' => [
            new PasswordField(
                name: 'field',
                label: $label,
                requiredError: $error,
                purpose: PasswordPurposeEnum::CURRENT,
            ),
            'field',
        ];
        yield 'phone number' => [
            new PhoneNumberField(name: 'field', label: $label, value: null, invalidErrorMessage: $error),
            'field',
        ];
    }

    #[DataProvider('fieldProvider')]
    public function testValueIsEmptyStringAfterValidationWithMissingKey(StringInputField $field, string $name): void
    {
        $field->validate(input: FormInput::fromArray(data: []));

        $this->assertSame('', $field->getValueAsString());
    }

    public function testPostedStringIsReturnedTrimmedAndUnencoded(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::fromHtml(html: 'Label'));

        $field->validate(input: FormInput::fromArray(data: ['field' => ' <a> ']));

        $this->assertSame('<a>', $field->getValueAsString());
    }

    public function testConstructorStringIsReturned(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::fromHtml(html: 'Label'), value: 'x');

        $this->assertSame('x', $field->getValueAsString());
    }

    public function testRejectedArrayInputResetsValue(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::fromHtml(html: 'Label'), value: 'x');

        $field->validate(input: FormInput::fromArray(data: ['field' => ['y']]));

        $this->assertSame('', $field->getValueAsString());
    }

    public function testPasswordFieldIsEmptyStringAfterConstruction(): void
    {
        $field = new PasswordField(
            name: 'password',
            label: HtmlText::fromHtml(html: 'Password'),
            requiredError: HtmlText::fromHtml(html: 'Required'),
            purpose: PasswordPurposeEnum::CURRENT,
        );

        $this->assertSame('', $field->getValueAsString());
    }

    public function testPhoneNumberFieldReturnsStoredValueNotRenderedFormat(): void
    {
        $field = new PhoneNumberField(
            name: 'phone',
            label: HtmlText::fromHtml(html: 'Phone'),
            value: null,
            invalidErrorMessage: HtmlText::fromHtml(html: 'Invalid'),
        );

        $field->validate(input: FormInput::fromArray(data: ['phone' => ' 044 668 18 00 ']));

        $this->assertSame('+41.446681800', $field->getValueAsString());
    }
}
