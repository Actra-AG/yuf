<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\db;

use actra\yuf\form\AmountParser;
use BackedEnum;
use DateTimeImmutable;
use ReflectionEnum;

/**
 * One fetched database row with typed getters. Narrows the untyped PDO values once, at the boundary: a missing
 * column, a NULL in a non-nullable getter or a value of the wrong type throws a `DbRowValueException`, nothing is
 * cast silently.
 *
 * Types that arrive from pdo_mysql with the attributes set by `FrameworkDb` (native prepared statements, no
 * stringified fetches): integer and tinyint columns as `int`, FLOAT/DOUBLE as `float`, DECIMAL, DATE, DATETIME and
 * TIMESTAMP as `string`, BIGINT UNSIGNED above PHP_INT_MAX as `string`. The getters also accept numeric strings, so
 * they keep working if a project switches to emulated prepares or stringified fetches.
 *
 * Date and time columns are parsed in the PHP default time zone (`date_default_timezone_get()`): the time zone of the
 * database session must match it, otherwise TIMESTAMP values are shifted.
 */
final readonly class DbRow
{
    /**
     * @param array<string, mixed> $values Column name => value, fetched with `PDO::FETCH_ASSOC`.
     */
    public function __construct(private array $values) {}

    public function has(string $column): bool
    {
        return array_key_exists(key: $column, array: $this->values);
    }

    public function getString(string $column): string
    {
        return $this->getNullableString(column: $column) ?? throw DbRowValueException::unexpectedNull(
            column: $column,
            expectedType: 'string',
        );
    }

    public function getNullableString(string $column): ?string
    {
        $value = $this->value(column: $column);
        if ($value === null) {
            return null;
        }

        return is_string(value: $value) ? $value : throw DbRowValueException::wrongType(
            column: $column,
            expectedType: 'string',
            actualType: get_debug_type(value: $value),
        );
    }

    public function getInt(string $column): int
    {
        return $this->getNullableInt(column: $column) ?? throw DbRowValueException::unexpectedNull(
            column: $column,
            expectedType: 'int',
        );
    }

    public function getNullableInt(string $column): ?int
    {
        $value = $this->value(column: $column);
        if ($value === null) {
            return null;
        }
        if (is_int(value: $value)) {
            return $value;
        }

        return $this->intFromString(column: $column, value: $value);
    }

    public function getFloat(string $column): float
    {
        return $this->getNullableFloat(column: $column) ?? throw DbRowValueException::unexpectedNull(
            column: $column,
            expectedType: 'float',
        );
    }

    public function getNullableFloat(string $column): ?float
    {
        $value = $this->value(column: $column);
        if ($value === null) {
            return null;
        }
        if (is_float(value: $value)) {
            return is_finite(num: $value) ? $value : throw DbRowValueException::invalidValue(
                column: $column,
                expectedType: 'float',
                reason: 'the value is not finite',
            );
        }
        if (is_int(value: $value)) {
            return (float) $value;
        }
        $this->assertString(column: $column, value: $value, expectedType: 'float');
        $float = AmountParser::toFloat(value: $value);
        if ($float === null || $value !== trim(string: $value)) {
            throw DbRowValueException::invalidValue(
                column: $column,
                expectedType: 'float',
                reason: 'not a plain decimal number',
            );
        }

        return $float;
    }

    /**
     * Returns the decimal as string (e.g. `'12.50'`), the same representation as `DecimalField::getValueAsDecimal()`.
     * yuf has no decimal value type, and a float would lose the exactness of the DECIMAL column. Floats are rejected
     * for the same reason.
     */
    public function getDecimal(string $column): string
    {
        return $this->getNullableDecimal(column: $column) ?? throw DbRowValueException::unexpectedNull(
            column: $column,
            expectedType: 'decimal',
        );
    }

    public function getNullableDecimal(string $column): ?string
    {
        $value = $this->value(column: $column);
        if ($value === null) {
            return null;
        }
        if (is_int(value: $value)) {
            return (string) $value;
        }
        $this->assertString(column: $column, value: $value, expectedType: 'decimal string');
        if (preg_match(pattern: '/^-?\d+(\.\d+)?$/', subject: $value) !== 1) {
            throw DbRowValueException::invalidValue(
                column: $column,
                expectedType: 'decimal',
                reason: 'not a plain decimal number like "12.50"',
            );
        }

        return $value;
    }

    /**
     * Accepts `0`, `1`, `'0'`, `'1'` (tinyint(1)) and real booleans.
     */
    public function getBool(string $column): bool
    {
        $value = $this->value(column: $column);
        if ($value === null) {
            throw DbRowValueException::unexpectedNull(column: $column, expectedType: 'bool');
        }
        if (is_bool(value: $value)) {
            return $value;
        }
        if (!is_int(value: $value) && !is_string(value: $value)) {
            throw DbRowValueException::wrongType(
                column: $column,
                expectedType: 'bool (0 or 1)',
                actualType: get_debug_type(value: $value),
            );
        }

        return match ($value) {
            0, '0' => false,
            1, '1' => true,
            default => throw DbRowValueException::invalidValue(
                column: $column,
                expectedType: 'bool',
                reason: 'only 0 and 1 are allowed',
            ),
        };
    }

    /**
     * For DATE (time 00:00:00), DATETIME and TIMESTAMP (with or without fractional seconds) columns.
     */
    public function getDateTimeImmutable(string $column): DateTimeImmutable
    {
        return $this->getNullableDateTimeImmutable(column: $column) ?? throw DbRowValueException::unexpectedNull(
            column: $column,
            expectedType: 'DateTimeImmutable',
        );
    }

    public function getNullableDateTimeImmutable(string $column): ?DateTimeImmutable
    {
        $value = $this->value(column: $column);
        if ($value === null) {
            return null;
        }
        $this->assertString(column: $column, value: $value, expectedType: 'date/time string');
        $format = match (true) {
            preg_match(pattern: '/^\d{4}-\d{2}-\d{2}$/', subject: $value) === 1 => '!Y-m-d',
            preg_match(pattern: '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(\.\d{1,6})?$/', subject: $value) === 1
            => '!Y-m-d H:i:s' . (str_contains(haystack: $value, needle: '.') ? '.u' : ''),
            default => throw DbRowValueException::invalidValue(
                column: $column,
                expectedType: 'date/time',
                reason: 'expected "Y-m-d", "Y-m-d H:i:s" or "Y-m-d H:i:s.u"',
            ),
        };
        $dateTime = DateTimeImmutable::createFromFormat(format: $format, datetime: $value);
        // Warnings catch overflowing values like "2026-02-30" and zero dates like "0000-00-00".
        if ($dateTime === false || DateTimeImmutable::getLastErrors() !== false) {
            throw DbRowValueException::invalidValue(
                column: $column,
                expectedType: 'date/time',
                reason: 'not an existing date or time',
            );
        }

        return $dateTime;
    }

    /**
     * @template T of BackedEnum
     * @param class-string<T> $enumClass
     *
     * @return T
     */
    public function getEnum(string $column, string $enumClass): BackedEnum
    {
        $enum = $this->getNullableEnum(column: $column, enumClass: $enumClass);

        return $enum ?? throw DbRowValueException::unexpectedNull(column: $column, expectedType: $enumClass);
    }

    /**
     * @template T of BackedEnum
     * @param class-string<T> $enumClass
     *
     * @return T|null
     */
    public function getNullableEnum(string $column, string $enumClass): ?BackedEnum
    {
        $value = $this->value(column: $column);
        if ($value === null) {
            return null;
        }
        $backingValue = (string) new ReflectionEnum(objectOrClass: $enumClass)->getBackingType() === 'int'
            ? $this->getInt(column: $column)
            : $this->getString(column: $column);

        return $enumClass::tryFrom($backingValue) ?? throw DbRowValueException::invalidValue(
            column: $column,
            expectedType: $enumClass,
            reason: 'unknown value "' . $backingValue . '"',
        );
    }

    private function value(string $column): mixed
    {
        if (!array_key_exists(key: $column, array: $this->values)) {
            throw DbRowValueException::missingColumn(column: $column);
        }

        return $this->values[$column];
    }

    private function intFromString(string $column, mixed $value): int
    {
        $this->assertString(column: $column, value: $value, expectedType: 'int');
        $int = preg_match(pattern: '/^-?\d+$/', subject: $value) === 1 ? AmountParser::toInt(value: $value) : null;

        return $int ?? throw DbRowValueException::invalidValue(
            column: $column,
            expectedType: 'int',
            reason: 'not an integer or out of the integer range',
        );
    }

    /**
     * @phpstan-assert string $value
     */
    private function assertString(string $column, mixed $value, string $expectedType): void
    {
        if (!is_string(value: $value)) {
            throw DbRowValueException::wrongType(
                column: $column,
                expectedType: $expectedType,
                actualType: get_debug_type(value: $value),
            );
        }
    }
}
