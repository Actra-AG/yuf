<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\PathVars;
use actra\yuf\exception\NotFoundException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PathVarsTest extends TestCase
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function validIntegers(): array
    {
        return [
            'positive' => ['42', 42],
            'zero' => ['0', 0],
            'negative' => ['-1', -1],
            'leading zeros' => ['007', 7],
            'max' => [(string)PHP_INT_MAX, PHP_INT_MAX],
            'min' => [(string)PHP_INT_MIN, PHP_INT_MIN],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidIntegers(): array
    {
        return [
            'letters' => ['abc'],
            'trailing letters' => ['12abc'],
            'empty' => [''],
            'leading space' => [' 12'],
            'trailing space' => ['12 '],
            'plus sign' => ['+12'],
            'decimal' => ['1.5'],
            'exponent' => ['1e3'],
            'minus only' => ['-'],
            'overflow' => ['9223372036854775808'],
            'underflow' => ['-9223372036854775809'],
            'very large' => ['99999999999999999999999999'],
        ];
    }

    #[DataProvider('validIntegers')]
    public function testGetAsIntReturnsIntegerForIntegerFormattedValue(string $value, int $expected): void
    {
        $this->assertSame($expected, $this->pathVarsWith(value: $value)->getAsInt(nr: 1));
    }

    #[DataProvider('invalidIntegers')]
    public function testGetAsIntReturnsNullForNonIntegerValue(string $value): void
    {
        $this->assertNull($this->pathVarsWith(value: $value)->getAsInt(nr: 1));
    }

    public function testGetAsIntReturnsNullForMissingVar(): void
    {
        $this->assertNull($this->pathVarsWith(value: '42')->getAsInt(nr: 2));
    }

    #[DataProvider('validIntegers')]
    public function testGetRequiredAsIntReturnsIntegerForIntegerFormattedValue(string $value, int $expected): void
    {
        $this->assertSame($expected, $this->pathVarsWith(value: $value)->getRequiredAsInt(nr: 1));
    }

    #[DataProvider('invalidIntegers')]
    public function testGetRequiredAsIntThrowsNotFoundForNonIntegerValue(string $value): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Path variable 1 is missing or not an integer');
        $this->pathVarsWith(value: $value)->getRequiredAsInt(nr: 1);
    }

    public function testGetRequiredAsIntThrowsNotFoundForMissingVar(): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionCode(404);
        $this->pathVarsWith(value: '42')->getRequiredAsInt(nr: 2);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validStrings(): array
    {
        return [
            'word' => ['abc', 'abc'],
            'number' => ['12', '12'],
            'zero' => ['0', '0'],
            'trimmed' => [' 12 ', '12'],
        ];
    }

    #[DataProvider('validStrings')]
    public function testGetRequiredAsStringReturnsTrimmedValue(string $value, string $expected): void
    {
        $this->assertSame($expected, $this->pathVarsWith(value: $value)->getRequiredAsString(nr: 1));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyStrings(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ['  '],
        ];
    }

    #[DataProvider('emptyStrings')]
    public function testGetRequiredAsStringThrowsNotFoundForEmptyValue(string $value): void
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Path variable 1 is missing or empty');
        $this->pathVarsWith(value: $value)->getRequiredAsString(nr: 1);
    }

    public function testGetRequiredAsStringThrowsNotFoundForMissingVar(): void
    {
        $this->expectException(NotFoundException::class);
        $this->pathVarsWith(value: 'abc')->getRequiredAsString(nr: 2);
    }

    public function testGetReturnsTrimmedValueOrNullLikeRequestHandler(): void
    {
        $pathVars = $this->pathVarsWith(value: ' 12 ');

        $this->assertSame('subscription', $pathVars->get(nr: 0));
        $this->assertSame('12', $pathVars->get(nr: 1));
        $this->assertNull($pathVars->get(nr: 2));
    }

    private function pathVarsWith(string $value): PathVars
    {
        return new PathVars(values: ['subscription', $value]);
    }
}