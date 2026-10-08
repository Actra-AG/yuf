<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\template\runtime;

use actra\yuf\template\runtime\ComparisonOperatorEnum;
use actra\yuf\template\runtime\TrustedHtml;
use actra\yuf\template\runtime\ValueComparator;
use actra\yuf\template\TemplateException;
use actra\yuf\tests\Double\template\StringableValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * The rules of docs/template-engine/design.md, section 3.1. The 320 cases of the characterization test
 * (`TemplateIfTagTest`) pin them through the engine; these tests pin the rules themselves.
 */
final class ValueComparatorTest extends TestCase
{
    private function compare(mixed $value, string $operator, string $against): bool
    {
        $operatorEnum = ComparisonOperatorEnum::tryFromAttribute(attribute: $operator);
        $this->assertNotNull($operatorEnum);

        return new ValueComparator()->compare(value: $value, operator: $operatorEnum, against: $against);
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function equalsNullProvider(): iterable
    {
        yield 'null' => [null, true];
        yield 'empty string' => ['', true];
        yield 'empty array' => [[], true];
        yield 'false' => [false, true];
        yield 'int 0' => [0, true];
        yield 'float 0' => [0.0, true];
        yield 'string 0' => ['0', false];
        yield 'string 0.0' => ['0.0', false];
        yield 'true' => [true, false];
        yield 'int 1' => [1, false];
        yield 'text' => ['null', false];
        yield 'array with an item' => [[0], false];
        yield 'object' => [new stdClass(), false];
        yield 'trusted empty HTML' => [new TrustedHtml(html: ''), true];
        yield 'trusted HTML 0' => [new TrustedHtml(html: '0'), false];
    }

    #[DataProvider('equalsNullProvider')]
    public function testEqualsNull(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->compare(value: $value, operator: 'eq', against: 'null'));
        $this->assertSame(!$expected, $this->compare(value: $value, operator: 'ne', against: 'null'));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function equalsEmptyStringProvider(): iterable
    {
        yield 'null' => [null, true];
        yield 'empty string' => ['', true];
        yield 'false' => [false, true];
        yield 'empty array' => [[], false];
        yield 'int 0' => [0, false];
        yield 'string 0' => ['0', false];
        yield 'space' => [' ', false];
        yield 'trusted empty HTML' => [new TrustedHtml(html: ''), true];
    }

    #[DataProvider('equalsEmptyStringProvider')]
    public function testEqualsEmptyString(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, $this->compare(value: $value, operator: 'eq', against: ''));
        $this->assertSame(!$expected, $this->compare(value: $value, operator: 'ne', against: ''));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function truthyProvider(): iterable
    {
        yield 'true' => [true, true];
        yield 'int 1' => [1, true];
        yield 'negative int' => [-1, true];
        yield 'float' => [0.5, true];
        yield 'text' => ['abc', true];
        yield 'text false' => ['false', true];
        yield 'array with an item' => [[0], true];
        yield 'object' => [new stdClass(), true];
        yield 'Stringable with text' => [new StringableValue(value: 'x'), true];
        yield 'trusted HTML' => [new TrustedHtml(html: 'x'), true];
        yield 'null' => [null, false];
        yield 'false' => [false, false];
        yield 'empty string' => ['', false];
        yield 'string 0' => ['0', false];
        yield 'int 0' => [0, false];
        yield 'float 0' => [0.0, false];
        yield 'empty array' => [[], false];
        yield 'Stringable with 0' => [new StringableValue(value: '0'), false];
        yield 'Stringable empty' => [new StringableValue(value: ''), false];
        yield 'trusted empty HTML' => [new TrustedHtml(html: ''), false];
    }

    #[DataProvider('truthyProvider')]
    public function testEqualsTrueAndFalse(mixed $value, bool $isTruthy): void
    {
        $this->assertSame($isTruthy, $this->compare(value: $value, operator: 'eq', against: 'true'));
        $this->assertSame(!$isTruthy, $this->compare(value: $value, operator: 'eq', against: 'false'));
        $this->assertSame(!$isTruthy, $this->compare(value: $value, operator: 'ne', against: 'true'));
        $this->assertSame($isTruthy, $this->compare(value: $value, operator: 'ne', against: 'false'));
    }

    public function testKeywordsAreCaseInsensitive(): void
    {
        $this->assertTrue($this->compare(value: null, operator: 'eq', against: 'NULL'));
        $this->assertTrue($this->compare(value: true, operator: 'eq', against: 'True'));
        $this->assertTrue($this->compare(value: 0, operator: 'eq', against: 'FALSE'));
    }

    /**
     * @return iterable<string, array{mixed, string, bool}>
     */
    public static function textComparisonProvider(): iterable
    {
        yield 'equal strings' => ['abc', 'abc', true];
        yield 'different case' => ['ABC', 'abc', false];
        yield 'int and its text' => [1, '1', true];
        yield 'int and another text' => [1, '01', false];
        yield 'zero int and 0' => [0, '0', true];
        yield 'float' => [1.5, '1.5', true];
        yield 'string 0 and 0' => ['0', '0', true];
        yield 'Stringable' => [new StringableValue(value: 'a b'), 'a b', true];
        yield 'trusted HTML' => [new TrustedHtml(html: '&amp;'), '&amp;', true];
        yield 'true is no text' => [true, '1', false];
        yield 'true and abc' => [true, 'abc', false];
        yield 'false and 0' => [false, '0', false];
        yield 'null and text' => [null, 'abc', false];
        yield 'array' => [['abc'], 'abc', false];
        yield 'object' => [new stdClass(), 'abc', false];
        yield 'text with spaces' => [' a ', 'a', false];
    }

    #[DataProvider('textComparisonProvider')]
    public function testEqualsComparesTheText(mixed $value, string $against, bool $expected): void
    {
        $this->assertSame($expected, $this->compare(value: $value, operator: 'eq', against: $against));
        $this->assertSame(!$expected, $this->compare(value: $value, operator: 'ne', against: $against));
    }

    /**
     * @return iterable<string, array{mixed, string, bool}>
     */
    public static function inProvider(): iterable
    {
        yield 'first item' => ['a', 'a b c', true];
        yield 'last item' => ['c', 'a b c', true];
        yield 'not an item' => ['d', 'a b c', false];
        yield 'part of an item' => ['a', 'ab c', false];
        yield 'int' => [2, '1 2 3', true];
        yield 'comma is no separator' => ['a', 'a,b', false];
        yield 'empty string and empty against' => ['', '', true];
        yield 'null is no text' => [null, '', false];
        yield 'null against is only text' => ['null', 'null', true];
        yield 'true against is only text' => ['true', 'true x', true];
        yield 'bool value' => [true, 'true', false];
        yield 'trusted HTML' => [new TrustedHtml(html: 'b'), 'a b', true];
        yield 'array' => [['a'], 'a', false];
    }

    #[DataProvider('inProvider')]
    public function testIn(mixed $value, string $against, bool $expected): void
    {
        $this->assertSame($expected, $this->compare(value: $value, operator: 'in', against: $against));
    }

    /**
     * @return iterable<string, array{mixed, string, string, bool}>
     */
    public static function numericProvider(): iterable
    {
        yield 'gt true' => [2, 'gt', '1', true];
        yield 'gt equal' => [1, 'gt', '1', false];
        yield 'ge equal' => [1, 'ge', '1', true];
        yield 'lt true' => [1, 'lt', '2', true];
        yield 'lt equal' => [2, 'lt', '2', false];
        yield 'le equal' => [2, 'le', '2', true];
        yield 'numeric string value' => ['10', 'gt', '9', true];
        yield 'numbers are not compared as text' => ['10', 'lt', '9', false];
        yield 'float value' => [1.5, 'gt', '1', true];
        yield 'float against' => [1, 'lt', '1.5', true];
        yield 'negative' => [-1, 'lt', '0', true];
        yield 'trusted numeric HTML' => [new TrustedHtml(html: '3'), 'ge', '3', true];
    }

    #[DataProvider('numericProvider')]
    public function testNumericComparison(mixed $value, string $operator, string $against, bool $expected): void
    {
        $this->assertSame($expected, $this->compare(value: $value, operator: $operator, against: $against));
    }

    /**
     * @return iterable<string, array{mixed, string, string}>
     */
    public static function nonNumericProvider(): iterable
    {
        yield 'null' => [null, 'gt', '1'];
        yield 'empty string' => ['', 'lt', '1'];
        yield 'text' => ['abc', 'ge', '1'];
        yield 'true' => [true, 'le', '1'];
        yield 'false' => [false, 'lt', '1'];
        yield 'array' => [[1], 'gt', '0'];
        yield 'object' => [new stdClass(), 'gt', '0'];
        yield 'Stringable' => [new StringableValue(value: '1'), 'gt', '0'];
        yield 'text against' => [1, 'gt', 'abc'];
        yield 'empty against' => [1, 'lt', ''];
    }

    #[DataProvider('nonNumericProvider')]
    public function testNumericComparisonOfAValueThatIsNotNumericThrows(mixed $value, string $operator, string $against): void
    {
        $this->expectException(TemplateException::class);

        $this->compare(value: $value, operator: $operator, against: $against);
    }

    public function testNonNumericValueMessage(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('The operators gt, ge, lt and le need a numeric value, got string');

        $this->compare(value: 'abc', operator: 'gt', against: '1');
    }

    public function testNonNumericAgainstMessage(): void
    {
        $this->expectException(TemplateException::class);
        $this->expectExceptionMessageIs('The operators gt, ge, lt and le need a numeric against attribute, got "abc"');

        $this->compare(value: 1, operator: 'gt', against: 'abc');
    }

    public function testOperatorAttributeIsCaseInsensitive(): void
    {
        $this->assertSame(ComparisonOperatorEnum::EQ, ComparisonOperatorEnum::tryFromAttribute(attribute: 'EQ'));
        $this->assertSame(ComparisonOperatorEnum::GE, ComparisonOperatorEnum::tryFromAttribute(attribute: 'Ge'));
        $this->assertNull(ComparisonOperatorEnum::tryFromAttribute(attribute: 'like'));
        $this->assertNull(ComparisonOperatorEnum::tryFromAttribute(attribute: ''));
    }
}
