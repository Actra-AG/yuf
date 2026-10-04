<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\AmountField;
use actra\yuf\form\component\field\EmailField;
use actra\yuf\form\component\field\IbanNumberField;
use actra\yuf\form\component\field\InputField;
use actra\yuf\form\component\field\PasswordField;
use actra\yuf\form\component\field\PhoneNumberField;
use actra\yuf\form\component\field\TextField;
use actra\yuf\form\component\field\ZipCodeField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * `getValueAsString()` of the InputField subclasses that are not covered by their own value test
 * (HiddenField, DateField and TimeField have tests in their value tests).
 */
final class InputFieldGetValueAsStringTest extends TestCase
{
    /**
     * @return iterable<string, array{InputField, string}>
     */
    public static function fieldProvider(): iterable
    {
        $label = HtmlText::encoded(textContent: 'Label');
        $error = HtmlText::encoded(textContent: 'Invalid');

        yield 'text' => [new TextField(name: 'field', label: $label), 'field'];
        yield 'email' => [
            new EmailField(name: 'field', label: $label, value: null, invalidError: $error, dnsCheck: false),
            'field',
        ];
        yield 'zip code' => [new ZipCodeField(name: 'field', label: $label), 'field'];
        yield 'iban' => [new IbanNumberField(name: 'field', label: $label, value: null, invalidError: $error), 'field'];
        yield 'password' => [
            new PasswordField(name: 'field', label: $label, requiredError: $error),
            'field',
        ];
        yield 'phone number' => [
            new PhoneNumberField(name: 'field', label: $label, value: null, invalidErrorMessage: $error),
            'field',
        ];
    }

    #[DataProvider('fieldProvider')]
    public function testValueIsEmptyStringAfterValidationWithMissingKey(InputField $field, string $name): void
    {
        $field->validate(inputData: []);

        $this->assertNull($field->getRawValue());
        $this->assertSame('', $field->getValueAsString());
    }

    public function testPostedStringIsReturnedUntrimmedAndUnencoded(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::encoded(textContent: 'Label'));

        $field->validate(inputData: ['field' => ' <a> ']);

        $this->assertSame(' <a> ', $field->getValueAsString());
    }

    public function testConstructorStringIsReturned(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::encoded(textContent: 'Label'), value: 'x');

        $this->assertSame('x', $field->getValueAsString());
    }

    public function testRejectedArrayInputKeepsPreviousValue(): void
    {
        $field = new TextField(name: 'field', label: HtmlText::encoded(textContent: 'Label'), value: 'x');

        $field->validate(inputData: ['field' => ['y']]);

        $this->assertSame('x', $field->getValueAsString());
    }

    public function testPasswordFieldIsEmptyStringAfterConstruction(): void
    {
        $field = new PasswordField(
            name: 'password',
            label: HtmlText::encoded(textContent: 'Password'),
            requiredError: HtmlText::encoded(textContent: 'Required')
        );

        $this->assertSame('', $field->getValueAsString());
    }

    public function testPhoneNumberFieldReturnsStoredValueNotRenderedFormat(): void
    {
        $field = new PhoneNumberField(
            name: 'phone',
            label: HtmlText::encoded(textContent: 'Phone'),
            value: null,
            invalidErrorMessage: HtmlText::encoded(textContent: 'Invalid')
        );

        $field->validate(inputData: ['phone' => ' 044 668 18 00 ']);

        $this->assertSame('+41.446681800', $field->getValueAsString());
    }

    /**
     * @return iterable<string, array{null|int|float, string}>
     */
    public static function amountInitialValueProvider(): iterable
    {
        yield 'null' => [null, ''];
        yield 'int' => [12, '12'];
        yield 'float' => [1.5, '1.5'];
        yield 'negative' => [-3, '-3'];
    }

    #[DataProvider('amountInitialValueProvider')]
    public function testAmountFieldReturnsInitialValueAsString(null|int|float $initialValue, string $expected): void
    {
        $field = new AmountField(
            name: 'amount',
            label: HtmlText::encoded(textContent: 'Amount'),
            valueIsFloat: true,
            initialValue: $initialValue
        );

        $this->assertSame($expected, $field->getValueAsString());
    }

    public function testAmountFieldReturnsPostedStringUntrimmed(): void
    {
        $field = new AmountField(
            name: 'amount',
            label: HtmlText::encoded(textContent: 'Amount'),
            valueIsFloat: true
        );

        $field->validate(inputData: ['amount' => ' 1.5 ']);

        $this->assertSame(' 1.5 ', $field->getValueAsString());
    }
}