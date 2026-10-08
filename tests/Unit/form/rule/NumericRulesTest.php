<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\rule;

use actra\yuf\form\rule\DecimalMaxRule;
use actra\yuf\form\rule\DecimalMinRule;
use actra\yuf\form\rule\DecimalRule;
use actra\yuf\form\rule\FloatMaxRule;
use actra\yuf\form\rule\FloatMinRule;
use actra\yuf\form\rule\IntegerMaxRule;
use actra\yuf\form\rule\IntegerMinRule;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class NumericRulesTest extends TestCase
{
    private static function message(): HtmlText
    {
        return HtmlText::fromHtml(html: 'Error');
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function integerMinProvider(): iterable
    {
        yield 'equal' => [5, 5, true];
        yield 'greater' => [5, 6, true];
        yield 'smaller' => [5, 4, false];
        yield 'negative limit' => [-5, -6, false];
    }

    #[DataProvider('integerMinProvider')]
    public function testIntegerMinRule(int $min, int $value, bool $expected): void
    {
        $rule = new IntegerMinRule(min: $min, errorMessage: NumericRulesTest::message());

        $this->assertSame($expected, $rule->validate(value: $value));
    }

    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function integerMaxProvider(): iterable
    {
        yield 'equal' => [5, 5, true];
        yield 'smaller' => [5, 4, true];
        yield 'greater' => [5, 6, false];
        yield 'int limits' => [PHP_INT_MAX, PHP_INT_MAX, true];
    }

    #[DataProvider('integerMaxProvider')]
    public function testIntegerMaxRule(int $max, int $value, bool $expected): void
    {
        $rule = new IntegerMaxRule(max: $max, errorMessage: NumericRulesTest::message());

        $this->assertSame($expected, $rule->validate(value: $value));
    }

    /**
     * @return iterable<string, array{float, float, bool}>
     */
    public static function floatProvider(): iterable
    {
        yield 'equal' => [1.5, 1.5, true];
        yield 'greater' => [1.5, 1.6, true];
        yield 'smaller' => [1.5, 1.4, false];
    }

    #[DataProvider('floatProvider')]
    public function testFloatMinRule(float $min, float $value, bool $expected): void
    {
        $rule = new FloatMinRule(min: $min, errorMessage: NumericRulesTest::message());

        $this->assertSame($expected, $rule->validate(value: $value));
    }

    /**
     * @return iterable<string, array{float, float, bool}>
     */
    public static function floatMaxProvider(): iterable
    {
        yield 'equal' => [1.5, 1.5, true];
        yield 'smaller' => [1.5, 1.4, true];
        yield 'greater' => [1.5, 1.6, false];
    }

    #[DataProvider('floatMaxProvider')]
    public function testFloatMaxRule(float $max, float $value, bool $expected): void
    {
        $rule = new FloatMaxRule(max: $max, errorMessage: NumericRulesTest::message());

        $this->assertSame($expected, $rule->validate(value: $value));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function decimalMinProvider(): iterable
    {
        yield 'equal' => ['0.05', '0.05', true];
        yield 'equal with other scale' => ['0.05', '0.050', true];
        yield 'greater' => ['0.05', '0.06', true];
        yield 'smaller' => ['0.05', '0.04', false];
        yield 'limit with sign' => ['+0.05', '0.05', true];
        yield 'negative' => ['-1.50', '-1.51', false];
        yield 'integer limit' => ['10', '9.99', false];
        yield 'beyond float precision' => ['12345678901234567890.01', '12345678901234567890.02', true];
        yield 'beyond float precision smaller' => ['12345678901234567890.02', '12345678901234567890.01', false];
    }

    #[DataProvider('decimalMinProvider')]
    public function testDecimalMinRule(string $min, string $value, bool $expected): void
    {
        $rule = new DecimalMinRule(min: $min, errorMessage: NumericRulesTest::message());

        $this->assertSame($expected, $rule->validate(value: $value));
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function decimalMaxProvider(): iterable
    {
        yield 'equal' => ['99.90', '99.90', true];
        yield 'smaller' => ['99.90', '99.89', true];
        yield 'greater' => ['99.90', '99.91', false];
        yield 'equal with other scale' => ['100', '100.00', true];
        yield 'beyond float precision' => ['12345678901234567890.01', '12345678901234567890.02', false];
    }

    #[DataProvider('decimalMaxProvider')]
    public function testDecimalMaxRule(string $max, string $value, bool $expected): void
    {
        $rule = new DecimalMaxRule(max: $max, errorMessage: NumericRulesTest::message());

        $this->assertSame($expected, $rule->validate(value: $value));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLimitProvider(): iterable
    {
        yield 'text' => ['abc'];
        yield 'empty' => [''];
        yield 'exponent' => ['1e3'];
        yield 'comma' => ['1,5'];
    }

    #[DataProvider('invalidLimitProvider')]
    public function testDecimalRuleRejectsALimitThatIsNoDecimal(string $limit): void
    {
        $this->expectException(InvalidArgumentException::class);

        new DecimalMinRule(min: $limit, errorMessage: NumericRulesTest::message());
    }

    public function testNumericRulesAreNotFinal(): void
    {
        foreach ([IntegerMinRule::class, IntegerMaxRule::class, FloatMinRule::class, FloatMaxRule::class,
            DecimalMinRule::class, DecimalMaxRule::class, DecimalRule::class] as $className) {
            $this->assertFalse(new ReflectionClass(objectOrClass: $className)->isFinal(), $className);
        }
    }
}
