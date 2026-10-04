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
use UnexpectedValueException;

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
        yield 'surrounding whitespace is accepted and trimmed' => [' 1.5 ', true];
        yield 'exponent notation is rejected' => ['1e3', false];
        yield 'decimal exponent notation is rejected' => ['1.5E-3', false];
        yield 'sign only' => ['-', false];
        yield 'whitespace inside' => ['1 5', false];
        yield 'text is rejected' => ['abc', false];
        yield 'decimal comma is rejected' => ['1,5', false];
    }

    #[DataProvider('floatInputProvider')]
    public function testFloatFieldStoresTrimmedInputAsString(string $input, bool $expectedValid): void
    {
        $field = $this->createField(valueIsFloat: true);

        $isValid = $field->validate(inputData: ['amount' => $input]);

        $this->assertSame($expectedValid, $isValid);
        $this->assertSame(trim(string: $input), $field->getRawValue());
    }

    /**
     * Integer fields only accept an optional sign and digits. Surrounding whitespace is accepted (the stored value is
     * trimmed), exponent notation and decimals are rejected.
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
    public function testIntegerFieldValidatesInputAndStoresItTrimmed(string $input, bool $expectedValid): void
    {
        $field = $this->createField(valueIsFloat: false);

        $isValid = $field->validate(inputData: ['amount' => $input]);

        $this->assertSame($expectedValid, $isValid);
        $this->assertSame(trim(string: $input), $field->getRawValue());
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

    public function testIntegerFieldRejectsArrayInputAndResetsValue(): void
    {
        $field = $this->createField(valueIsFloat: false, initialValue: 5);

        $this->assertFalse($field->validate(inputData: ['amount' => ['1']]));
        $this->assertSame('', $field->getRawValue());
    }

    public function testFloatFieldAcceptsEmptyValue(): void
    {
        $field = $this->createField(valueIsFloat: true);

        $this->assertTrue($field->validate(inputData: ['amount' => '']));
    }

    public function testValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: 5);

        $this->assertTrue($field->validate(inputData: []));
        $this->assertSame('', $field->getRawValue());
    }

    public function testArrayInputIsRejectedAndResetsValue(): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: 5);

        $isValid = $field->validate(inputData: ['amount' => ['x']]);

        $this->assertFalse($isValid);
        $this->assertSame('', $field->getRawValue());
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

    public function testNumericFieldValueIsEmptyStringAfterValidationWithMissingKey(): void
    {
        $field = new NumericField(
            name: 'number',
            label: HtmlText::encoded(textContent: 'Number')
        );

        $field->validate(inputData: []);

        $this->assertSame('', $field->getRawValue());
    }

    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function integerGetterProvider(): iterable
    {
        yield 'empty' => ['', null];
        yield 'whitespace only' => [" \t\n", null];
        yield 'integer' => ['12', 12];
        yield 'zero' => ['0', 0];
        yield 'negative' => ['-5', -5];
        yield 'plus sign' => ['+5', 5];
        yield 'leading zeros' => ['007', 7];
        yield 'surrounding whitespace' => [" \t12\n", 12];
        yield 'int max' => [(string)PHP_INT_MAX, PHP_INT_MAX];
        yield 'int min' => [(string)PHP_INT_MIN, PHP_INT_MIN];
    }

    #[DataProvider('integerGetterProvider')]
    public function testGetValueAsIntAfterValidation(string $input, ?int $expected): void
    {
        $field = $this->createField(valueIsFloat: false);

        $this->assertTrue($field->validate(inputData: ['amount' => $input]));
        $this->assertSame($expected, $field->getValueAsInt());
    }

    #[DataProvider('integerGetterProvider')]
    public function testGetValueAsFloatAcceptsIntegerFormats(string $input, ?int $expected): void
    {
        $field = $this->createField(valueIsFloat: true);

        $this->assertTrue($field->validate(inputData: ['amount' => $input]));
        $this->assertSame($expected === null ? null : (float)$expected, $field->getValueAsFloat());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonIntegerProvider(): iterable
    {
        yield 'decimal' => ['1.5'];
        yield 'decimal with zero' => ['1.0'];
        yield 'exponent' => ['1e3'];
        yield 'text' => ['abc'];
        yield 'overflow' => ['9223372036854775808'];
        yield 'negative overflow' => ['-9223372036854775809'];
    }

    #[DataProvider('nonIntegerProvider')]
    public function testGetValueAsIntThrowsForNonIntegerValue(string $input): void
    {
        $field = $this->createField(valueIsFloat: true);
        $field->validate(inputData: ['amount' => $input]);

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('field amount');

        $field->getValueAsInt();
    }

    /**
     * @return iterable<string, array{bool, string}>
     */
    public static function outOfRangeProvider(): iterable
    {
        yield 'integer field above int range' => [false, '9223372036854775808'];
        yield 'integer field below int range' => [false, '-9223372036854775809'];
        yield 'float field too large for float' => [true, str_repeat(string: '9', times: 400)];
    }

    #[DataProvider('outOfRangeProvider')]
    public function testValueOutOfRangeIsInvalid(bool $valueIsFloat, string $input): void
    {
        $field = $this->createField(valueIsFloat: $valueIsFloat);

        $this->assertFalse($field->validate(inputData: ['amount' => $input]));
    }

    public function testIntegerFieldAcceptsIntRangeLimits(): void
    {
        $field = $this->createField(valueIsFloat: false);

        $this->assertTrue($field->validate(inputData: ['amount' => '9223372036854775807']));
        $this->assertSame(PHP_INT_MAX, $field->getValueAsInt());
        $this->assertTrue($field->validate(inputData: ['amount' => '-9223372036854775808']));
        $this->assertSame(PHP_INT_MIN, $field->getValueAsInt());
    }

    public function testGetValueAsIntMessageNamesOverflow(): void
    {
        $field = $this->createField(valueIsFloat: true);
        $field->validate(inputData: ['amount' => '9223372036854775808']);

        $this->expectExceptionMessage('out of the integer range');

        $field->getValueAsInt();
    }

    public function testGetValueAsIntIsNullForNumericFieldWithoutValue(): void
    {
        $field = new NumericField(name: 'number', label: HtmlText::encoded(textContent: 'Number'));

        $this->assertNull($field->getValueAsInt());
        $this->assertNull($field->getValueAsFloat());
    }

    public function testGetValueAsIntOfNumericField(): void
    {
        $field = new NumericField(name: 'number', label: HtmlText::encoded(textContent: 'Number'));

        $this->assertTrue($field->validate(inputData: ['number' => ' -42 ']));
        $this->assertSame(-42, $field->getValueAsInt());
        $this->assertSame(-42.0, $field->getValueAsFloat());
    }

    public function testGettersAreNullAfterValidationWithMissingKey(): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: 5);

        $field->validate(inputData: []);

        $this->assertNull($field->getValueAsInt());
        $this->assertNull($field->getValueAsFloat());
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function floatGetterProvider(): iterable
    {
        yield 'decimal' => ['1.5', 1.5];
        yield 'negative decimal' => ['-1.5', -1.5];
        yield 'plus sign' => ['+1.5', 1.5];
        yield 'leading zeros' => ['007.50', 7.5];
        yield 'trailing dot' => ['1.', 1.0];
        yield 'leading dot' => ['.5', 0.5];
        yield 'surrounding whitespace' => [' 1.5 ', 1.5];
        yield 'integer above int range' => ['9223372036854775808', 9223372036854775808.0];
    }

    #[DataProvider('floatGetterProvider')]
    public function testGetValueAsFloatAfterValidation(string $input, float $expected): void
    {
        $field = $this->createField(valueIsFloat: true);

        $this->assertTrue($field->validate(inputData: ['amount' => $input]));
        $this->assertSame($expected, $field->getValueAsFloat());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonNumericProvider(): iterable
    {
        yield 'text' => ['abc'];
        yield 'exponent' => ['1e3'];
        yield 'decimal comma' => ['1,5'];
        yield 'sign only' => ['-'];
        yield 'too large for float' => [str_repeat(string: '9', times: 400)];
    }

    #[DataProvider('nonNumericProvider')]
    public function testGettersThrowForNonNumericValueAfterFailedValidation(string $input): void
    {
        $field = $this->createField(valueIsFloat: true);

        $field->validate(inputData: ['amount' => $input]);

        try {
            $field->getValueAsFloat();
            $this->fail('getValueAsFloat() must throw.');
        } catch (UnexpectedValueException $exception) {
            $this->assertStringContainsString('field amount', $exception->getMessage());
        }

        $this->expectException(UnexpectedValueException::class);

        $field->getValueAsInt();
    }

    public function testGettersAreNullAfterRejectedArrayInput(): void
    {
        $field = $this->createField(valueIsFloat: false, initialValue: 5);

        $this->assertFalse($field->validate(inputData: ['amount' => ['1']]));
        $this->assertNull($field->getValueAsInt());
        $this->assertNull($field->getValueAsFloat());
    }

    public function testGettersWorkBeforeValidationWithConstructorValue(): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: 1.5);

        $this->assertSame(1.5, $field->getValueAsFloat());
    }

    public function testGetValueAsIntThrowsForFloatConstructorValue(): void
    {
        $field = $this->createField(valueIsFloat: true, initialValue: 1.5);

        $this->expectException(UnexpectedValueException::class);

        $field->getValueAsInt();
    }

    public function testGetValueAsIntReturnsIntConstructorValue(): void
    {
        $field = $this->createField(valueIsFloat: false, initialValue: -3);

        $this->assertSame(-3, $field->getValueAsInt());
    }

    public function testGettersAreNullBeforeValidationWithoutConstructorValue(): void
    {
        $field = $this->createField(valueIsFloat: true);

        $this->assertNull($field->getValueAsInt());
        $this->assertNull($field->getValueAsFloat());
    }
}