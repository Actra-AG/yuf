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
        yield 'integer string' => ['42', 42.0];
        yield 'dot' => ['1.5', 1.5];
        yield 'comma' => ['1,5', 1.5];
        yield 'exponent' => ['1.5E2', 150.0];
        yield 'zero' => ['0.00', 0.0];
    }

    #[DataProvider('validInputProvider')]
    public function testSanitizeReturnsFloat(float|int|string $input, float $expectedValue): void
    {
        $this->assertSame($expectedValue, FloatSanitizer::sanitize(input: $input));
    }

    public function testSanitizeThrowsForValueThatIsCastToZero(): void
    {
        $this->expectException(RuntimeException::class);

        FloatSanitizer::sanitize(input: '1E-400');
    }
}
