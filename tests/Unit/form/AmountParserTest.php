<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form;

use actra\yuf\form\AmountParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function integerProvider(): iterable
    {
        yield 'digit' => ['5', 5];
        yield 'zero' => ['0', 0];
        yield 'negative zero' => ['-0', 0];
        yield 'negative' => ['-5', -5];
        yield 'plus sign' => ['+5', 5];
        yield 'leading zeros' => ['007', 7];
        yield 'only zeros' => ['000', 0];
        yield 'surrounding whitespace' => [" \t\n\r\v\f12 \t\n\r\v\f", 12];
        yield 'int max' => [(string)PHP_INT_MAX, PHP_INT_MAX];
        yield 'int min' => [(string)PHP_INT_MIN, PHP_INT_MIN];
        yield 'int max with leading zeros' => ['+00' . PHP_INT_MAX, PHP_INT_MAX];
        yield 'int max plus one' => ['9223372036854775808', null];
        yield 'int min minus one' => ['-9223372036854775809', null];
        yield 'huge' => [str_repeat(string: '9', times: 400), null];
    }

    #[DataProvider('integerProvider')]
    public function testToInt(string $value, ?int $expected): void
    {
        $this->assertSame($expected, AmountParser::toInt(value: $value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function integerFormatProvider(): iterable
    {
        foreach (AmountParserTest::integerProvider() as $name => [$value]) {
            yield $name => [$value];
        }
    }

    #[DataProvider('integerFormatProvider')]
    public function testIntegerFormatIsAcceptedEvenIfOutOfRange(string $value): void
    {
        $this->assertTrue(AmountParser::isInteger(value: $value));
        $this->assertTrue(AmountParser::isDecimal(value: $value));
    }

    /**
     * @return iterable<string, array{string, float}>
     */
    public static function decimalProvider(): iterable
    {
        yield 'integer' => ['5', 5.0];
        yield 'decimal' => ['1.5', 1.5];
        yield 'negative decimal' => ['-1.5', -1.5];
        yield 'plus sign' => ['+1.5', 1.5];
        yield 'trailing dot' => ['1.', 1.0];
        yield 'leading dot' => ['.5', 0.5];
        yield 'leading zeros' => ['007.50', 7.5];
        yield 'surrounding whitespace' => [' 1.5 ', 1.5];
        yield 'integer above int range' => ['9223372036854775808', 9223372036854775808.0];
    }

    #[DataProvider('decimalProvider')]
    public function testToFloat(string $value, float $expected): void
    {
        $this->assertSame($expected, AmountParser::toFloat(value: $value));
        $this->assertTrue(AmountParser::isDecimal(value: $value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function decimalOnlyProvider(): iterable
    {
        yield 'decimal' => ['1.5'];
        yield 'trailing dot' => ['1.'];
        yield 'leading dot' => ['.5'];
        yield 'decimal with zero' => ['1.0'];
    }

    #[DataProvider('decimalOnlyProvider')]
    public function testDecimalIsNotAnInteger(string $value): void
    {
        $this->assertFalse(AmountParser::isInteger(value: $value));
        $this->assertNull(AmountParser::toInt(value: $value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'whitespace only' => ['  '];
        yield 'sign only' => ['-'];
        yield 'dot only' => ['.'];
        yield 'double sign' => ['+-5'];
        yield 'whitespace after sign' => ['- 5'];
        yield 'whitespace inside' => ['1 5'];
        yield 'exponent' => ['1e3'];
        yield 'decimal exponent' => ['1.5E-3'];
        yield 'hex' => ['0x1A'];
        yield 'thousands separator' => ["1'000"];
        yield 'decimal comma' => ['1,5'];
        yield 'text' => ['abc'];
        yield 'trailing text' => ['5a'];
        yield 'two dots' => ['1.2.3'];
        yield 'trailing newline inside' => ["5\nx"];
        yield 'nul byte' => ["5\0"];
        yield 'infinity' => ['INF'];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidValuesAreRejected(string $value): void
    {
        $this->assertFalse(AmountParser::isInteger(value: $value));
        $this->assertFalse(AmountParser::isDecimal(value: $value));
        $this->assertNull(AmountParser::toInt(value: $value));
        $this->assertNull(AmountParser::toFloat(value: $value));
    }

    public function testToFloatRejectsValueTooLargeForFloat(): void
    {
        $this->assertNull(AmountParser::toFloat(value: str_repeat(string: '9', times: 400)));
    }
}