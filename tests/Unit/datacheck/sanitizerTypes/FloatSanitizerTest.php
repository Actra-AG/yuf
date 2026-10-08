<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\datacheck\sanitizerTypes;

use actra\yuf\datacheck\sanitizerTypes\FloatSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FloatSanitizerTest extends TestCase
{
    /**
     * @return iterable<string, array{float|int|string, float}>
     */
    public static function validInputProvider(): iterable
    {
        yield 'float' => [1.5, 1.5];
        yield 'int' => [2, 2.0];
        yield 'negative int' => [-3, -3.0];
        yield 'largest int' => [PHP_INT_MAX, 9.223372036854776E+18];
        yield 'integer string' => ['42', 42.0];
        yield 'integer string of 100' => ['100', 100.0];
        yield 'negative integer string' => ['-5', -5.0];
        yield 'negative integer string of 100' => ['-100', -100.0];
        yield 'integer string with leading zeros' => ['0123', 123.0];
        yield 'two leading zeros' => ['007', 7.0];
        yield 'negative with leading zeros' => ['-0123', -123.0];
        yield 'zero string' => ['0', 0.0];
        yield 'zeros' => ['000', 0.0];
        yield 'negative zero string' => ['-0', 0.0];
        yield 'surrounding whitespace' => [' 7 ', 7.0];
        yield 'surrounding line break' => ["5\n", 5.0];
        yield 'dot' => ['1.5', 1.5];
        yield 'negative dot' => ['-1.5', -1.5];
        yield 'comma' => ['1,5', 1.5];
        yield 'negative comma' => ['-1,5', -1.5];
        yield 'without integer part' => ['.5', 0.5];
        yield 'without fraction' => ['5.', 5.0];
        yield 'exponent' => ['1.5E2', 150.0];
        yield 'lower case exponent' => ['1.5e2', 150.0];
        yield 'integer with exponent' => ['1e5', 100000.0];
        yield 'comma with exponent' => ['1,5E2', 150.0];
        yield 'negative exponent' => ['25E-1', 2.5];
        yield 'zero' => ['0.00', 0.0];
        yield 'zero with exponent' => ['0E5', 0.0];
        yield 'negative zero' => ['-0.0', 0.0];
        yield 'zero comma' => ['0,0', 0.0];
    }

    #[DataProvider('validInputProvider')]
    public function testSanitizeReturnsFloat(float|int|string $input, float $expectedValue): void
    {
        $this->assertSame($expectedValue, FloatSanitizer::sanitize(input: $input));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidInputProvider(): iterable
    {
        yield 'text' => ['abc'];
        yield 'two dots' => ['1.5.5'];
        yield 'two commas' => ['1,5,5'];
        yield 'dot and comma (thousands separator)' => ['1,000.5'];
        yield 'plus sign' => ['+5'];
        yield 'underscore' => ['1_000'];
        yield 'plus sign in the exponent' => ['1.5E+2'];
        yield 'two exponents' => ['1E2E3'];
        yield 'Arabic-Indic digits' => ['١٢'];
        yield 'underflow to zero' => ['1E-400'];
        yield 'overflow to infinity' => ['1E400'];
        yield 'negative overflow to infinity' => ['-1E400'];
        yield 'digits beyond the float range' => [str_repeat(string: '9', times: 400)];
        yield 'empty' => [''];
        yield 'only whitespace' => ['  '];
        yield 'only a minus sign' => ['-'];
        yield 'only a dot' => ['.'];
        yield 'only a comma' => [','];
        yield 'exponent without a base' => ['E5'];
        yield 'empty exponent' => ['1E'];
        yield 'exponent with only a minus sign' => ['1E-'];
        yield 'exponent with a fraction' => ['1E2.5'];
        yield 'minus sign inside' => ['1-5'];
    }

    #[DataProvider('invalidInputProvider')]
    public function testSanitizeThrowsForInvalidInput(string $input): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageIs('Value is not suitable as FLOAT.');

        FloatSanitizer::sanitize(input: $input);
    }

    public function testSanitizeDoesNotDependOnTheLocaleOfTheProcess(): void
    {
        $previousLocale = setlocale(LC_NUMERIC, '0');
        if (setlocale(LC_NUMERIC, 'de_DE.UTF-8', 'de_DE', 'de_CH.UTF-8', 'fr_FR.UTF-8') === false) {
            self::markTestSkipped('No locale with a decimal comma is installed.');
        }
        try {
            $this->assertSame(1.5, FloatSanitizer::sanitize(input: '1.5'));
            $this->assertSame(1.5, FloatSanitizer::sanitize(input: '1,5'));
            $this->assertSame(1500.0, FloatSanitizer::sanitize(input: '1.5E3'));
        } finally {
            setlocale(LC_NUMERIC, $previousLocale === false ? 'C' : $previousLocale);
        }
    }
}
