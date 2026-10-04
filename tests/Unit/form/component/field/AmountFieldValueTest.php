<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\component\field;

use actra\yuf\form\component\field\AmountField;
use actra\yuf\form\component\field\NumericField;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of AmountField and NumericField. The value is always stored as string (or null).
 */
final class AmountFieldValueTest extends TestCase
{
    private function createField(bool $valueIsFloat, null|int|float $initialValue = null): AmountField
    {
        return new AmountField(
            name: 'amount',
            label: HtmlText::encoded(textContent: 'Amount'),
            valueIsFloat: $valueIsFloat,
            initialValue: $initialValue
        );
    }

    /**
     * @return iterable<string, array{null|int|float, string}>
     */
    public static function initialValueProvider(): iterable
    {
        yield 'null becomes empty string' => [null, ''];
        yield 'int' => [12, '12'];
        yield 'zero int' => [0, '0'];
        yield 'negative int' => [-3, '-3'];
        yield 'float' => [1.5, '1.5'];
    }

    #[DataProvider('initialValueProvider')]
    public function testInitialValueIsStoredAsString(null|int|float $initialValue, string $expected): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: $initialValue);

        $this->assertSame($expected, $field->getRawValue());
    }

    /**
     * Posted values are stored as posted: no trimming, no number conversion.
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function floatInputProvider(): iterable
    {
        yield 'integer string' => ['12', true];
        yield 'decimal string' => ['1.5', true];
        yield 'negative decimal' => ['-1.5', true];
        yield 'plus sign' => ['+1.5', true];
        yield 'leading zeros' => ['007.50', true];
        yield 'trailing dot' => ['1.', true];
        yield 'leading dot' => ['.5', true];
        yield 'surrounding whitespace is accepted and kept' => [' 1.5 ', true];
        yield 'exponent notation is rejected' => ['1e3', false];
        yield 'decimal exponent notation is rejected' => ['1.5E-3', false];
        yield 'sign only' => ['-', false];
        yield 'whitespace inside' => ['1 5', false];
        yield 'text is rejected' => ['abc', false];
        yield 'decimal comma is rejected' => ['1,5', false];
    }

    #[DataProvider('floatInputProvider')]
    public function testFloatFieldStoresInputAsString(string $input, bool $expectedValid): void
    {
        $field = $this->createField(valueIsFloat: true);

        $isValid = $field->validate(inputData: ['amount' => $input]);

        $this->assertSame($expectedValid, $isValid);
        $this->assertSame($input, $field->getRawValue());
    }

    /**
     * Integer fields only accept an optional sign and digits. Surrounding whitespace is accepted (the stored value is
     * not trimmed), exponent notation and decimals are rejected.
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function integerInputProvider(): iterable
    {
        yield 'integer string' => ['12', true];
        yield 'zero' => ['0', true];
        yield 'negative' => ['-5', true];
        yield 'plus sign' => ['+5', true];
        yield 'leading zeros' => ['007', true];
        yield 'surrounding whitespace' => [' 12 ', true];
        yield 'empty string' => ['', true];
        yield 'only whitespace' => ['  ', true];
        yield 'decimal string' => ['1.5', false];
        yield 'decimal with zero' => ['1.0', false];
        yield 'trailing dot' => ['1.', false];
        yield 'leading dot' => ['.5', false];
        yield 'exponent notation' => ['1e3', false];
        yield 'sign only' => ['-', false];
        yield 'double sign' => ['+-5', false];
        yield 'whitespace between sign and digits' => ['- 5', false];
        yield 'thousands separator' => ["1'000", false];
        yield 'text' => ['abc', false];
        yield 'hex' => ['0x1A', false];
    }

    #[DataProvider('integerInputProvider')]
    public function testIntegerFieldValidatesInputAndStoresItAsPosted(string $input, bool $expectedValid): void
    {
        $field = $this->createField(valueIsFloat: false);

        $isValid = $field->validate(inputData: ['amount' => $input]);

        $this->assertSame($expectedValid, $isValid);
        $this->assertSame($input, $field->getRawValue());
    }

    public function testIntegerFieldAcceptsIntegerConstructorValue(): void
    {
        $field = $this->createField(valueIsFloat: false, initialValue: -3);

        $this->assertTrue($field->validate(inputData: ['amount' => '-3']));
    }

    public function testIntegerFieldRejectsFloatConstructorValueWithoutPosting(): void
    {
        $field = $this->createField(valueIsFloat: false, initialValue: 1.5);

        $this->assertFalse($field->validate(inputData: ['amount' => '1.5']));
    }

    public function testIntegerFieldRejectsArrayInputAndKeepsInitialValue(): void
    {
        $field = $this->createField(valueIsFloat: false, initialValue: 5);

        $this->assertFalse($field->validate(inputData: ['amount' => ['1']]));
        $this->assertSame('5', $field->getRawValue());
    }

    public function testFloatFieldAcceptsEmptyValue(): void
    {
        $field = $this->createField(valueIsFloat: true);

        $this->assertTrue($field->validate(inputData: ['amount' => '']));
    }

    public function testValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: 5);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertNull($field->getRawValue());
    }

    public function testArrayInputIsRejectedAndKeepsInitialStringValue(): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: 5);

        $isValid = $field->validate(inputData: ['amount' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('5', $field->getRawValue());
    }

    public function testNumericFieldRejectsDecimalsLikeIntegerAmountField(): void
    {
        $field = new NumericField(
            name: 'number',
            label: HtmlText::encoded(textContent: 'Number'),
            initialValue: 7
        );

        $this->assertSame('7', $field->getRawValue());

        $isValid = $field->validate(inputData: ['number' => '1.5']);

        $this->assertFalse($isValid);
        $this->assertSame('1.5', $field->getRawValue());
    }

    public function testNumericFieldValueIsNullAfterValidationWithMissingKey(): void
    {
        $field = new NumericField(
            name: 'number',
            label: HtmlText::encoded(textContent: 'Number')
        );

        $this->assertSame('', $field->getRawValue());

        $field->validate(inputData: []);

        $this->assertNull($field->getRawValue());
    }
}