<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\form\rule;

use actra\yuf\form\rule\MaxCountRule;
use actra\yuf\form\rule\MinCountRule;
use actra\yuf\html\HtmlText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class StringListRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{int, list<string>, bool}>
     */
    public static function countProvider(): iterable
    {
        yield 'exactly' => [2, ['a', 'b'], true];
        yield 'more' => [2, ['a', 'b', 'c'], false];
        yield 'fewer' => [2, ['a'], true];
    }

    /**
     * @param list<string> $values
     */
    #[DataProvider('countProvider')]
    public function testMaxCountRule(int $maxCount, array $values, bool $expected): void
    {
        $rule = new MaxCountRule(maxCount: $maxCount, errorMessage: HtmlText::fromHtml(html: 'Error'));

        $this->assertSame($expected, $rule->validate(values: $values));
    }

    /**
     * @return iterable<string, array{int, list<string>, bool}>
     */
    public static function minCountProvider(): iterable
    {
        yield 'exactly' => [2, ['a', 'b'], true];
        yield 'more' => [2, ['a', 'b', 'c'], true];
        yield 'fewer' => [2, ['a'], false];
    }

    /**
     * @param list<string> $values
     */
    #[DataProvider('minCountProvider')]
    public function testMinCountRule(int $minCount, array $values, bool $expected): void
    {
        $rule = new MinCountRule(minCount: $minCount, errorMessage: HtmlText::fromHtml(html: 'Error'));

        $this->assertSame($expected, $rule->validate(values: $values));
    }

    public function testCountRulesAreNotFinal(): void
    {
        $this->assertFalse(new ReflectionClass(objectOrClass: MinCountRule::class)->isFinal());
        $this->assertFalse(new ReflectionClass(objectOrClass: MaxCountRule::class)->isFinal());
    }
}
