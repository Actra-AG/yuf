<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\sanitizerTypes;

use actra\yuf\datacheck\sanitizerTypes\IntegerSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class IntegerSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{int|float|string, int}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'int' => [5, 5];
        yield 'negative int' => [-7, -7];
        yield 'whole float' => [5.0, 5];
        yield 'integer string' => ['5', 5];
        yield 'negative integer string' => ['-5', -5];
        yield 'leading zeros' => ['007', 7];
        yield 'minus zero' => ['-0', 0];
        yield 'surrounding whitespace' => [' 5 ', 5];
        yield 'smallest int as float' => [-9.2233720368547758E+18, PHP_INT_MIN];
        yield 'largest int' => ['9223372036854775807', PHP_INT_MAX];
        yield 'smallest int' => ['-9223372036854775808', PHP_INT_MIN];
        yield 'exponent' => ['1e3', 1000];
        yield 'whole decimal' => ['1.0', 1];
        yield 'whole decimal with leading zero' => ['05.0', 5];
        yield 'large exponent within range' => ['5e18', 5000000000000000000];
    }

    #[DataProvider('validInputProvider')]
    public function testSanitizeReturnsInt(int|float|string $input, int $expectedValue): void
    {
        $this->assertSame($expectedValue, IntegerSanitizer::sanitize(input: $input));
    }

    /**
     * @return iterable<string, array{int|float|string, string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'text' => ['abc', 'Value is not suitable as INT.'];
        yield 'empty' => ['', 'Value is not suitable as INT.'];
        yield 'only whitespace' => [' ', 'Value is not suitable as INT.'];
        yield 'hexadecimal' => ['0x1A', 'Value is not suitable as INT.'];
        yield 'plus sign' => ['+5', 'Value is not suitable as INT.'];
        yield 'decimal comma' => ['1,0', 'Value is not suitable as INT.'];
        yield 'one more than the largest int' => ['9223372036854775808', 'Value is out of range as INT.'];
        yield 'one less than the smallest int' => ['-9223372036854775809', 'Value is out of range as INT.'];
        yield 'exponent out of range' => ['1e19', 'Value is out of range as INT.'];
        yield 'two to the power of 63 as float' => [9.2233720368547758E+18, 'Value is out of range as INT.'];
        yield 'two to the power of 63 as exponent' => ['9.2233720368547758e18', 'Value is out of range as INT.'];
        yield 'exponent beyond float range' => ['1e400', 'Value is not suitable as INT.'];
        yield 'negative exponent beyond float range' => ['-1e400', 'Value is not suitable as INT.'];
        yield 'float out of range' => [1.0E+30, 'Value is out of range as INT.'];
        yield 'infinite float' => [INF, 'Value is out of range as INT.'];
        yield 'fraction string' => ['1.5', 'Value is not a whole number.'];
        yield 'fraction without integer part' => ['.5', 'Value is not a whole number.'];
        yield 'fraction float' => [5.5, 'Value is not a whole number.'];
        yield 'not a number' => [NAN, 'Value is not a whole number.'];
    }

    #[DataProvider('invalidInputProvider')]
    public function testSanitizeThrowsForInvalidInput(int|float|string $input, string $expectedMessage): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs($expectedMessage);

        IntegerSanitizer::sanitize(input: $input);
    }
}
