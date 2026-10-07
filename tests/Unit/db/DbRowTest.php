<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbRow;
use actra\yuf\db\DbRowValueException;
use actra\yuf\tests\Double\db\LevelEnum;
use actra\yuf\tests\Double\db\StatusEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DbRowTest extends TestCase
{
    public function testHasReportsExistingColumnsEvenIfNull(): void
    {
        $row = new DbRow(values: ['a' => null]);

        $this->assertTrue($row->has(column: 'a'));
        $this->assertFalse($row->has(column: 'b'));
    }

    public function testMissingColumnThrowsAndNamesTheColumn(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "nope" does not exist');
        new DbRow(values: [])->getString(column: 'nope');
    }

    public function testNullInNonNullableGetterThrows(): void
    {
        $row = new DbRow(values: ['c' => null]);
        $getters = [
            fn() => $row->getString(column: 'c'),
            fn() => $row->getInt(column: 'c'),
            fn() => $row->getFloat(column: 'c'),
            fn() => $row->getDecimal(column: 'c'),
            fn() => $row->getBool(column: 'c'),
            fn() => $row->getDateTimeImmutable(column: 'c'),
            fn() => $row->getEnum(column: 'c', enumClass: StatusEnum::class),
        ];
        foreach ($getters as $getter) {
            try {
                $getter();
                DbRowTest::fail('Expected DbRowValueException');
            } catch (DbRowValueException $exception) {
                $this->assertStringContainsString('Column "c" is NULL', $exception->getMessage());
            }
        }
    }

    public function testNullableGettersReturnNullForNull(): void
    {
        $row = new DbRow(values: ['c' => null]);

        $this->assertNull($row->getNullableString(column: 'c'));
        $this->assertNull($row->getNullableInt(column: 'c'));
        $this->assertNull($row->getNullableFloat(column: 'c'));
        $this->assertNull($row->getNullableDecimal(column: 'c'));
        $this->assertNull($row->getNullableDateTimeImmutable(column: 'c'));
        $this->assertNull($row->getNullableEnum(column: 'c', enumClass: StatusEnum::class));
    }

    public function testStringGetter(): void
    {
        $row = new DbRow(values: ['s' => 'text', 'empty' => '', 'i' => 5]);

        $this->assertSame('text', $row->getString(column: 's'));
        $this->assertSame('', $row->getNullableString(column: 'empty'));
    }

    public function testStringGetterRejectsNonStringAndNamesTypes(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessageIsOrContains('Column "i" has the type int, but expected string');
        new DbRow(values: ['i' => 5])->getString(column: 'i');
    }

    /**
     * @return array<string, array{mixed, int}>
     */
    public static function validIntegers(): array
    {
        return [
            'int' => [12, 12],
            'negative int' => [-12, -12],
            'zero' => [0, 0],
            'string' => ['12', 12],
            'negative string' => ['-12', -12],
            'unsigned bigint within range as string' => ['9223372036854775807', PHP_INT_MAX],
        ];
    }

    #[DataProvider('validIntegers')]
    public function testIntGetterAcceptsIntegersAndIntegerStrings(mixed $value, int $expected): void
    {
        $row = new DbRow(values: ['n' => $value]);

        $this->assertSame($expected, $row->getInt(column: 'n'));
        $this->assertSame($expected, $row->getNullableInt(column: 'n'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidIntegers(): array
    {
        return [
            'float' => [1.0],
            'decimal string' => ['1.5'],
            'text' => ['abc'],
            'empty string' => [''],
            'padded string' => [' 12'],
            'plus sign' => ['+12'],
            'exponent' => ['1e3'],
            'overflowing string' => ['18446744073709551615'],
            'bool' => [true],
            'array' => [[]],
        ];
    }

    #[DataProvider('invalidIntegers')]
    public function testIntGetterRejectsEverythingElse(mixed $value): void
    {
        $this->expectException(DbRowValueException::class);
        new DbRow(values: ['n' => $value])->getInt(column: 'n');
    }

    /**
     * @return array<string, array{mixed, float}>
     */
    public static function validFloats(): array
    {
        return [
            'float' => [1.5, 1.5],
            'int' => [3, 3.0],
            'decimal string' => ['12.50', 12.5],
            'integer string' => ['-7', -7.0],
            'leading point' => ['.5', 0.5],
        ];
    }

    #[DataProvider('validFloats')]
    public function testFloatGetter(mixed $value, float $expected): void
    {
        $this->assertSame($expected, new DbRow(values: ['f' => $value])->getFloat(column: 'f'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidFloats(): array
    {
        return [
            'text' => ['abc'],
            'padded' => [' 1.5'],
            'exponent' => ['1e3'],
            'nan' => [NAN],
            'infinity' => [INF],
            'bool' => [false],
        ];
    }

    #[DataProvider('invalidFloats')]
    public function testFloatGetterRejectsEverythingElse(mixed $value): void
    {
        $this->expectException(DbRowValueException::class);
        new DbRow(values: ['f' => $value])->getFloat(column: 'f');
    }

    public function testDecimalGetterKeepsTheCanonicalString(): void
    {
        $row = new DbRow(values: ['a' => '12.50', 'b' => '-0.10', 'c' => 7, 'd' => '100']);

        $this->assertSame('12.50', $row->getDecimal(column: 'a'));
        $this->assertSame('-0.10', $row->getDecimal(column: 'b'));
        $this->assertSame('7', $row->getDecimal(column: 'c'));
        $this->assertSame('100', $row->getNullableDecimal(column: 'd'));
    }

    #[DataProvider('invalidDecimals')]
    public function testDecimalGetterRejectsEverythingElse(mixed $value): void
    {
        $this->expectException(DbRowValueException::class);
        new DbRow(values: ['d' => $value])->getDecimal(column: 'd');
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDecimals(): array
    {
        return [
            'float loses exactness' => [1.5],
            'text' => ['abc'],
            'padded' => ['1.5 '],
            'trailing point' => ['1.'],
            'exponent' => ['1e3'],
        ];
    }

    #[DataProvider('validBools')]
    public function testBoolGetter(mixed $value, bool $expected): void
    {
        $this->assertSame($expected, new DbRow(values: ['b' => $value])->getBool(column: 'b'));
    }

    /**
     * @return array<string, array{mixed, bool}>
     */
    public static function validBools(): array
    {
        return [
            'int 0' => [0, false],
            'int 1' => [1, true],
            'string 0' => ['0', false],
            'string 1' => ['1', true],
            'bool false' => [false, false],
            'bool true' => [true, true],
        ];
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidBools(): array
    {
        return [
            'int 2' => [2],
            'int -1' => [-1],
            'string 2' => ['2'],
            'text' => ['true'],
            'empty' => [''],
            'float' => [1.0],
        ];
    }

    #[DataProvider('invalidBools')]
    public function testBoolGetterRejectsEverythingElse(mixed $value): void
    {
        $this->expectException(DbRowValueException::class);
        new DbRow(values: ['b' => $value])->getBool(column: 'b');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validDateTimes(): array
    {
        return [
            'DATE' => ['2026-10-04', '2026-10-04 00:00:00.000000'],
            'DATETIME' => ['2026-10-04 13:14:15', '2026-10-04 13:14:15.000000'],
            'DATETIME(3)' => ['2026-10-04 13:14:15.250', '2026-10-04 13:14:15.250000'],
            'DATETIME(6)' => ['2026-10-04 13:14:15.123456', '2026-10-04 13:14:15.123456'],
            'leap day' => ['2028-02-29', '2028-02-29 00:00:00.000000'],
        ];
    }

    #[DataProvider('validDateTimes')]
    public function testDateTimeGetterParsesInTheDefaultTimeZone(string $value, string $expected): void
    {
        $dateTime = new DbRow(values: ['d' => $value])->getDateTimeImmutable(column: 'd');

        $this->assertSame($expected, $dateTime->format(format: 'Y-m-d H:i:s.u'));
        $this->assertSame(date_default_timezone_get(), $dateTime->getTimezone()->getName());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidDateTimes(): array
    {
        return [
            'zero date' => ['0000-00-00'],
            'zero datetime' => ['0000-00-00 00:00:00'],
            'overflowing day' => ['2026-02-30'],
            'invalid month' => ['2026-13-01'],
            'invalid hour' => ['2026-10-04 25:00:00'],
            'iso with T' => ['2026-10-04T13:14:15'],
            'german format' => ['04.10.2026'],
            'time only' => ['13:14:15'],
            'too many decimals' => ['2026-10-04 13:14:15.1234567'],
            'int timestamp' => [1_800_000_000],
            'padded' => [' 2026-10-04'],
        ];
    }

    #[DataProvider('invalidDateTimes')]
    public function testDateTimeGetterRejectsEverythingElse(mixed $value): void
    {
        $this->expectException(DbRowValueException::class);
        new DbRow(values: ['d' => $value])->getDateTimeImmutable(column: 'd');
    }

    public function testEnumGetterFindsStringAndIntBackedCases(): void
    {
        $row = new DbRow(values: ['s' => 'blocked', 'i' => 2, 'is' => '1']);

        $this->assertSame(StatusEnum::Blocked, $row->getEnum(column: 's', enumClass: StatusEnum::class));
        $this->assertSame(LevelEnum::High, $row->getEnum(column: 'i', enumClass: LevelEnum::class));
        $this->assertSame(LevelEnum::Low, $row->getNullableEnum(column: 'is', enumClass: LevelEnum::class));
    }

    public function testEnumGetterThrowsOnUnknownValue(): void
    {
        $this->expectException(DbRowValueException::class);
        $this->expectExceptionMessageIsOrContains('unknown value "deleted"');
        new DbRow(values: ['s' => 'deleted'])->getEnum(column: 's', enumClass: StatusEnum::class);
    }

    public function testEnumGetterThrowsOnWrongType(): void
    {
        $this->expectException(DbRowValueException::class);
        new DbRow(values: ['s' => 1])->getEnum(column: 's', enumClass: StatusEnum::class);
    }

    public function testIntBackedEnumGetterThrowsOnUnknownValue(): void
    {
        $this->expectException(DbRowValueException::class);
        new DbRow(values: ['i' => 3])->getEnum(column: 'i', enumClass: LevelEnum::class);
    }
}
