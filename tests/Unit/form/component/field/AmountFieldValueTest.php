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
        yield 'surrounding whitespace is accepted and kept' => [' 1.5 ', true];
        yield 'exponent notation is accepted and kept' => ['1e3', true];
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
     * KNOWN BUG (fixed in Task 2): ValidAmountRule checks is_float() on the posted string, which is never true, so
     * integer fields accept decimals, too.
     */
    public function testIntegerFieldAcceptsDecimalStringBecauseOfKnownBug(): void
    {
        $field = $this->createField(valueIsFloat: false);

        $isValid = $field->validate(inputData: ['amount' => '1.5']);

        $this->assertTrue($isValid);
        $this->assertSame('1.5', $field->getRawValue());
    }

    public function testIntegerFieldAcceptsIntegerString(): void
    {
        $field = $this->createField(valueIsFloat: false);

        $this->assertTrue($field->validate(inputData: ['amount' => '12']));
        $this->assertSame('12', $field->getRawValue());
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

    public function testNumericFieldBehavesLikeIntegerAmountField(): void
    {
        $field = new NumericField(
            name: 'number',
            label: HtmlText::encoded(textContent: 'Number'),
            initialValue: 7
        );

        $this->assertSame('7', $field->getRawValue());

        $isValid = $field->validate(inputData: ['number' => '1.5']);

        // KNOWN BUG (fixed in Task 2): decimals are accepted by the integer rule.
        $this->assertTrue($isValid);
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