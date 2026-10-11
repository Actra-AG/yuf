<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Write counterpart of `DbRow::getDateTimeImmutable()`: formats a point in time for a DATE, DATETIME or TIMESTAMP
 * column in the PHP default time zone (`date_default_timezone_get()`), the time zone `DbRow` parses them in. A value
 * written with this class reads back as the same point in time, whatever time zone the given object has (e.g. UTC
 * from an external API). `$date->format('Y-m-d H:i:s')` instead writes the wall-clock time of the object's own time
 * zone. Fractional seconds are cut off. The given object is not modified.
 */
final class DbDateTime
{
    /**
     * For DATETIME and TIMESTAMP columns: `Y-m-d H:i:s` in the PHP default time zone.
     */
    public static function toDateTimeString(DateTimeInterface $dateTime): string
    {
        return DbDateTime::toDefaultTimeZone(dateTime: $dateTime)->format(format: 'Y-m-d H:i:s');
    }

    public static function toNullableDateTimeString(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime === null ? null : DbDateTime::toDateTimeString(dateTime: $dateTime);
    }

    /**
     * For DATE columns: `Y-m-d` of the date in the PHP default time zone.
     */
    public static function toDateString(DateTimeInterface $dateTime): string
    {
        return DbDateTime::toDefaultTimeZone(dateTime: $dateTime)->format(format: 'Y-m-d');
    }

    public static function toNullableDateString(?DateTimeInterface $dateTime): ?string
    {
        return $dateTime === null ? null : DbDateTime::toDateString(dateTime: $dateTime);
    }

    private static function toDefaultTimeZone(DateTimeInterface $dateTime): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface(object: $dateTime)->setTimezone(
            timezone: new DateTimeZone(timezone: date_default_timezone_get()),
        );
    }
}
