<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\BooleanSearchOperatorEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BooleanSearchOperatorEnumTest extends TestCase
{
    public function testTryFromShorthand(): void
    {
        $this->assertSame(BooleanSearchOperatorEnum::AND, BooleanSearchOperatorEnum::tryFromShorthand(character: '+'));
        $this->assertSame(BooleanSearchOperatorEnum::NOT, BooleanSearchOperatorEnum::tryFromShorthand(character: '-'));
        $this->assertNull(BooleanSearchOperatorEnum::tryFromShorthand(character: 'a'));
        $this->assertNull(BooleanSearchOperatorEnum::tryFromShorthand(character: ''));
    }

    /**
     * @return iterable<string, array{string, ?BooleanSearchOperatorEnum}>
     */
    public static function wordProvider(): iterable
    {
        yield 'and' => ['and', BooleanSearchOperatorEnum::AND];
        yield 'or' => ['or', BooleanSearchOperatorEnum::OR];
        yield 'not' => ['not', BooleanSearchOperatorEnum::NOT];
        yield 'upper case' => ['OR', null];
        yield 'other word' => ['xor', null];
    }

    #[DataProvider('wordProvider')]
    public function testValuesAreTheWordsOfTheSearchText(string $word, ?BooleanSearchOperatorEnum $expected): void
    {
        $this->assertSame($expected, BooleanSearchOperatorEnum::tryFrom(value: $word));
    }

    public function testSqlConnector(): void
    {
        $this->assertSame(' AND ', BooleanSearchOperatorEnum::AND->sqlConnector());
        $this->assertSame(' AND ', BooleanSearchOperatorEnum::NOT->sqlConnector());
        $this->assertSame(' OR ', BooleanSearchOperatorEnum::OR->sqlConnector());
    }

    public function testOnlyNotIsNegated(): void
    {
        $this->assertTrue(BooleanSearchOperatorEnum::NOT->isNegated());
        $this->assertFalse(BooleanSearchOperatorEnum::AND->isNegated());
        $this->assertFalse(BooleanSearchOperatorEnum::OR->isNegated());
    }
}
