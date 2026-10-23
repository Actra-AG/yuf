<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\AmountFormatter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function swissDecimalProvider(): iterable
    {
        yield 'below a thousand' => ['10.00', '10.00'];
        yield 'one thousand' => ['1000.00', '1\'000.00'];
        yield 'premium price' => ['27839.32', '27\'839.32'];
        yield 'millions' => ['1234567.80', '1\'234\'567.80'];
        yield 'negative' => ['-2500.50', '-2\'500.50'];
        yield 'negative below a thousand' => ['-999.99', '-999.99'];
        yield 'integer string' => ['1234', '1\'234'];
        yield 'zero' => ['0', '0'];
        yield 'negative zero has no sign' => ['-0.00', '0.00'];
        yield 'leading zeros are removed' => ['007.50', '7.50'];
        yield 'decimals are kept' => ['1234.5678', '1\'234.5678'];
        yield 'beyond int' => ['123456789012345678901234567890.12', '123\'456\'789\'012\'345\'678\'901\'234\'567\'890.12'];
    }

    #[DataProvider('swissDecimalProvider')]
    public function testSwissFormatsDecimals(string $amount, string $expected): void
    {
        $this->assertSame($expected, AmountFormatter::swiss()->formatDecimal(amount: $amount));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function scaleProvider(): iterable
    {
        yield 'pads with zeros' => ['1000', 2, '1\'000.00'];
        yield 'pads one decimal' => ['1000.5', 2, '1\'000.50'];
        yield 'rounds down' => ['1.004', 2, '1.00'];
        yield 'rounds half up' => ['1.005', 2, '1.01'];
        yield 'rounds half away from zero when negative' => ['-1.005', 2, '-1.01'];
        yield 'rounding carries into a new group' => ['999.995', 2, '1\'000.00'];
        yield 'scale 0 rounds to an integer' => ['1234.5', 0, '1\'235'];
        yield 'rounded to zero has no sign' => ['-0.004', 2, '0.00'];
    }

    #[DataProvider('scaleProvider')]
    public function testScalePadsOrRounds(string $amount, int $scale, string $expected): void
    {
        $this->assertSame($expected, AmountFormatter::swiss()->formatDecimal(amount: $amount, scale: $scale));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAmountProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'thousands separator' => ['1,000.00'];
        yield 'apostrophe' => ["1'000.00"];
        yield 'comma as decimal separator' => ['10,50'];
        yield 'plus sign' => ['+10'];
        yield 'trailing dot' => ['10.'];
        yield 'leading dot' => ['.5'];
        yield 'exponent' => ['1e3'];
        yield 'whitespace' => [' 10'];
        yield 'trailing newline' => ["10\n"];
        yield 'text' => ['abc'];
    }

    #[DataProvider('invalidAmountProvider')]
    public function testInvalidAmountIsRejected(string $amount): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmountFormatter::swiss()->formatDecimal(amount: $amount);
    }

    public function testNegativeScaleIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        AmountFormatter::swiss()->formatDecimal(amount: '10', scale: -1);
    }

    /**
     * @return iterable<string, array{int, string}>
     */
    public static function intProvider(): iterable
    {
        yield 'zero' => [0, '0'];
        yield 'below a thousand' => [999, '999'];
        yield 'one thousand' => [1000, '1\'000'];
        yield 'millions' => [1234567, '1\'234\'567'];
        yield 'negative' => [-1234567, '-1\'234\'567'];
        yield 'maximum' => [PHP_INT_MAX, '9\'223\'372\'036\'854\'775\'807'];
        yield 'minimum' => [PHP_INT_MIN, '-9\'223\'372\'036\'854\'775\'808'];
    }

    #[DataProvider('intProvider')]
    public function testSwissFormatsIntegers(int $amount, string $expected): void
    {
        $this->assertSame($expected, AmountFormatter::swiss()->formatInt(amount: $amount));
    }

    public function testGermanUsesDotAndComma(): void
    {
        $this->assertSame('-1.234.567,80', AmountFormatter::german()->formatDecimal(amount: '-1234567.80'));
    }

    public function testEnglishUsesCommaAndDot(): void
    {
        $this->assertSame('-1,234,567.80', AmountFormatter::english()->formatDecimal(amount: '-1234567.80'));
    }

    public function testOwnSeparators(): void
    {
        $formatter = new AmountFormatter(thousandsSeparator: '’', decimalSeparator: '.');

        $this->assertSame('27’839.32', $formatter->formatDecimal(amount: '27839.32'));
    }

    public function testEmptyThousandsSeparatorDoesNotGroup(): void
    {
        $formatter = new AmountFormatter(thousandsSeparator: '', decimalSeparator: ',');

        $this->assertSame('1234567,80', $formatter->formatDecimal(amount: '1234567.80'));
    }

    public function testEmptyDecimalSeparatorIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AmountFormatter(thousandsSeparator: '\'', decimalSeparator: '');
    }

    public function testEqualSeparatorsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AmountFormatter(thousandsSeparator: '.', decimalSeparator: '.');
    }
}
