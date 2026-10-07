<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\common;

use actra\yuf\common\TimeOfDay;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ValueError;

final class TimeOfDayTest extends TestCase
{
    public function testConstructorStoresTheParts(): void
    {
        $time = new TimeOfDay(hour: 8, minute: 5, second: 7);

        $this->assertSame(8, $time->hour);
        $this->assertSame(5, $time->minute);
        $this->assertSame(7, $time->second);
    }

    public function testSecondDefaultsToZero(): void
    {
        $this->assertSame(0, new TimeOfDay(hour: 8, minute: 5)->second);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function limitProvider(): iterable
    {
        yield 'start of the day' => [0, 0, 0];
        yield 'end of the day' => [23, 59, 59];
    }

    #[DataProvider('limitProvider')]
    public function testLimitsAreAccepted(int $hour, int $minute, int $second): void
    {
        $time = new TimeOfDay(hour: $hour, minute: $minute, second: $second);

        $this->assertSame([$hour, $minute, $second], [$time->hour, $time->minute, $time->second]);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function outOfRangeProvider(): iterable
    {
        yield 'negative hour' => [-1, 0, 0, 'hour'];
        yield 'hour 24' => [24, 0, 0, 'hour'];
        yield 'negative minute' => [0, -1, 0, 'minute'];
        yield 'minute 60' => [0, 60, 0, 'minute'];
        yield 'negative second' => [0, 0, -1, 'second'];
        yield 'second 60' => [0, 0, 60, 'second'];
    }

    #[DataProvider('outOfRangeProvider')]
    public function testOutOfRangePartThrows(int $hour, int $minute, int $second, string $part): void
    {
        $this->expectException(ValueError::class);
        $this->expectExceptionMessageIsOrContains('The ' . $part);

        new TimeOfDay(hour: $hour, minute: $minute, second: $second);
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function validStringProvider(): iterable
    {
        yield 'hours and minutes' => ['08:05', 8, 5, 0];
        yield 'with seconds' => ['08:05:07', 8, 5, 7];
        yield 'midnight' => ['00:00', 0, 0, 0];
        yield 'midnight with seconds' => ['00:00:00', 0, 0, 0];
        yield 'last minute' => ['23:59', 23, 59, 0];
        yield 'last second' => ['23:59:59', 23, 59, 59];
        yield 'noon' => ['12:00', 12, 0, 0];
        yield 'hour 19' => ['19:30', 19, 30, 0];
    }

    #[DataProvider('validStringProvider')]
    public function testFromStringParsesTimes(string $time, int $hour, int $minute, int $second): void
    {
        $this->assertTrue(
            TimeOfDay::fromString(time: $time)?->equals(
                other: new TimeOfDay(hour: $hour, minute: $minute, second: $second),
            ) === true,
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidStringProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'hour 24' => ['24:00'];
        yield 'hour 25' => ['25:00'];
        yield 'minute 60' => ['08:60'];
        yield 'second 60' => ['08:00:60'];
        yield 'single digit hour' => ['8:30'];
        yield 'single digit minute' => ['08:3'];
        yield 'no colon' => ['0830'];
        yield 'only hour' => ['08'];
        yield 'trailing colon' => ['08:30:'];
        yield 'milliseconds' => ['08:30:00.5'];
        yield 'whitespace around' => [' 08:30 '];
        yield 'trailing newline' => ["08:30\n"];
        yield 'text' => ['noon'];
        yield 'negative' => ['-08:30'];
        yield 'with date' => ['2020-01-02 08:30'];
        yield 'am pm' => ['08:30 pm'];
    }

    #[DataProvider('invalidStringProvider')]
    public function testFromStringReturnsNullForInvalidStrings(string $time): void
    {
        $this->assertNull(TimeOfDay::fromString(time: $time));
    }

    public function testToStringHasSeconds(): void
    {
        $this->assertSame('08:05:00', new TimeOfDay(hour: 8, minute: 5)->toString());
        $this->assertSame('23:59:59', new TimeOfDay(hour: 23, minute: 59, second: 59)->toString());
        $this->assertSame('00:00:00', new TimeOfDay(hour: 0, minute: 0)->toString());
    }

    public function testToShortStringDropsTheSeconds(): void
    {
        $this->assertSame('08:05', new TimeOfDay(hour: 8, minute: 5, second: 30)->toShortString());
        $this->assertSame('00:00', new TimeOfDay(hour: 0, minute: 0)->toShortString());
    }

    public function testToStringCanBeParsedAgain(): void
    {
        $time = new TimeOfDay(hour: 7, minute: 8, second: 9);

        $this->assertTrue(TimeOfDay::fromString(time: $time->toString())?->equals(other: $time) === true);
    }

    public function testEqualsComparesAllParts(): void
    {
        $time = new TimeOfDay(hour: 8, minute: 30, second: 15);

        $this->assertTrue($time->equals(other: new TimeOfDay(hour: 8, minute: 30, second: 15)));
        $this->assertFalse($time->equals(other: new TimeOfDay(hour: 9, minute: 30, second: 15)));
        $this->assertFalse($time->equals(other: new TimeOfDay(hour: 8, minute: 31, second: 15)));
        $this->assertFalse($time->equals(other: new TimeOfDay(hour: 8, minute: 30, second: 16)));
    }

    public function testEqualsTreatsMissingSecondsAsZero(): void
    {
        $this->assertTrue(
            new TimeOfDay(hour: 8, minute: 30)->equals(other: new TimeOfDay(hour: 8, minute: 30, second: 0)),
        );
    }
}
