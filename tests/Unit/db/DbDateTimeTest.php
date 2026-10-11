<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\db;

use actra\yuf\db\DbDateTime;
use actra\yuf\db\DbRow;
use DateTime;
use DateTimeImmutable;
use Override;
use PHPUnit\Framework\TestCase;

final class DbDateTimeTest extends TestCase
{
    private string $originalTimeZone;

    #[Override]
    protected function setUp(): void
    {
        $this->originalTimeZone = date_default_timezone_get();
        date_default_timezone_set(timezoneId: 'Europe/Zurich');
    }

    #[Override]
    protected function tearDown(): void
    {
        date_default_timezone_set(timezoneId: $this->originalTimeZone);
    }

    public function testUtcIsConvertedToTheDefaultTimeZone(): void
    {
        $utc = new DateTimeImmutable('2026-10-11T17:14:08Z');

        $this->assertSame('2026-10-11 19:14:08', DbDateTime::toDateTimeString(dateTime: $utc));
    }

    public function testDateInTheDefaultTimeZoneIsUnchanged(): void
    {
        $local = new DateTimeImmutable('2026-10-11 19:14:08');

        $this->assertSame('2026-10-11 19:14:08', DbDateTime::toDateTimeString(dateTime: $local));
    }

    public function testOffsetOfTheDaylightSavingTimeBoundaryIsRespected(): void
    {
        // Switch to winter time: 2026-10-25 01:00 UTC.
        $this->assertSame(
            '2026-10-25 02:59:59',
            DbDateTime::toDateTimeString(dateTime: new DateTimeImmutable('2026-10-25T00:59:59Z')),
        );
        $this->assertSame(
            '2026-10-25 02:00:00',
            DbDateTime::toDateTimeString(dateTime: new DateTimeImmutable('2026-10-25T01:00:00Z')),
        );
        // Winter time (UTC+1).
        $this->assertSame(
            '2026-12-01 13:00:00',
            DbDateTime::toDateTimeString(dateTime: new DateTimeImmutable('2026-12-01T12:00:00Z')),
        );
    }

    public function testDateStringUsesTheDateOfTheDefaultTimeZone(): void
    {
        $utc = new DateTimeImmutable('2026-10-11T23:30:00Z');

        $this->assertSame('2026-10-12', DbDateTime::toDateString(dateTime: $utc));
    }

    public function testMutableDateTimeIsNotModified(): void
    {
        $utc = new DateTime('2026-10-11T17:14:08Z');

        DbDateTime::toDateTimeString(dateTime: $utc);
        DbDateTime::toDateString(dateTime: $utc);

        $this->assertSame('2026-10-11T17:14:08+00:00', $utc->format(format: DATE_ATOM));
    }

    public function testNullableVariantsPassNullThrough(): void
    {
        $this->assertNull(DbDateTime::toNullableDateTimeString(dateTime: null));
        $this->assertNull(DbDateTime::toNullableDateString(dateTime: null));
        $utc = new DateTimeImmutable('2026-10-11T17:14:08Z');
        $this->assertSame('2026-10-11 19:14:08', DbDateTime::toNullableDateTimeString(dateTime: $utc));
        $this->assertSame('2026-10-11', DbDateTime::toNullableDateString(dateTime: $utc));
    }

    public function testRoundTripWithDbRowKeepsThePointInTime(): void
    {
        $utc = new DateTimeImmutable('2026-10-11T17:14:08Z');
        $row = new DbRow(values: ['created' => DbDateTime::toDateTimeString(dateTime: $utc)]);

        $this->assertSame(
            $utc->getTimestamp(),
            $row->getDateTimeImmutable(column: 'created')->getTimestamp(),
        );
    }
}
