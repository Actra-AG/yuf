<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use ValueError;

/**
 * A time of the day without a date and a time zone (e.g. opening hours), from 00:00:00 to 23:59:59.
 */
final readonly class TimeOfDay
{
    /**
     * @throws ValueError If the hour is not in 0..23 or the minute or the second is not in 0..59.
     */
    public function __construct(public int $hour, public int $minute, public int $second = 0)
    {
        if ($hour < 0 || $hour > 23) {
            throw new ValueError(message: 'The hour must be between 0 and 23, ' . $hour . ' given.');
        }
        if ($minute < 0 || $minute > 59) {
            throw new ValueError(message: 'The minute must be between 0 and 59, ' . $minute . ' given.');
        }
        if ($second < 0 || $second > 59) {
            throw new ValueError(message: 'The second must be between 0 and 59, ' . $second . ' given.');
        }
    }

    /**
     * Parses `H:i` or `H:i:s` (two digits each, 00:00 to 23:59:59). Returns `null` for anything else.
     */
    public static function fromString(string $time): ?TimeOfDay
    {
        $pattern = '/^([01]\d|2[0-3]):([0-5]\d)(?::([0-5]\d))?$/D';
        if (preg_match(pattern: $pattern, subject: $time, matches: $matches) !== 1) {
            return null;
        }

        return new TimeOfDay(
            hour: (int) $matches[1],
            minute: (int) $matches[2],
            second: array_key_exists(key: 3, array: $matches) ? (int) $matches[3] : 0,
        );
    }

    /**
     * Returns `H:i:s`, e.g. `08:05:00`.
     */
    public function toString(): string
    {
        return $this->toShortString() . ':' . sprintf('%02d', $this->second);
    }

    /**
     * Returns `H:i`, e.g. `08:05` (the seconds are dropped).
     */
    public function toShortString(): string
    {
        return sprintf('%02d:%02d', $this->hour, $this->minute);
    }

    public function equals(TimeOfDay $other): bool
    {
        return $this->hour === $other->hour
            && $this->minute === $other->minute
            && $this->second === $other->second;
    }
}
