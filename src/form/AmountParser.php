<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

/**
 * Pure parser for plain decimal numbers typed into number fields. Single source of truth for the formats accepted by
 * the number fields (`IntegerField`, `FloatField`, `DecimalField`, `HiddenIntegerField`).
 *
 * Accepted: optional sign and digits (integer), additionally `1.5`, `1.` and `.5` (decimal). Surrounding whitespace
 * (`" \t\n\r\v\f"`, like is_numeric()) is ignored. Not accepted: exponent notation, hex, thousands separators,
 * whitespace inside the number.
 */
final class AmountParser
{
    private const string WHITESPACE = " \t\n\r\v\f";

    public static function isInteger(string $value): bool
    {
        return preg_match(pattern: '/^[+-]?\d+$/', subject: AmountParser::trim(value: $value)) === 1;
    }

    /**
     * Integers are decimals too.
     */
    public static function isDecimal(string $value): bool
    {
        return preg_match(pattern: '/^[+-]?(\d+(\.\d*)?|\.\d+)$/', subject: AmountParser::trim(value: $value)) === 1;
    }

    /**
     * Returns `null` if the value is not an integer (see isInteger()) or does not fit into an `int`, instead of
     * saturating at PHP_INT_MAX/PHP_INT_MIN or turning into a float.
     */
    public static function toInt(string $value): ?int
    {
        if (!AmountParser::isInteger(value: $value)) {
            return null;
        }

        $trimmed = AmountParser::trim(value: $value);
        $digits = ltrim(string: $trimmed, characters: '+-');
        $digits = ltrim(string: $digits, characters: '0');
        $digits = $digits === '' ? '0' : $digits;
        $normalized = ($trimmed[0] === '-' && $digits !== '0' ? '-' : '') . $digits;

        $result = (int) $trimmed;

        // An overflowing numeric string is cast to the nearest limit, which differs from the normalized digits.
        return (string) $result === $normalized ? $result : null;
    }

    /**
     * Returns `null` if the value is not a decimal (see isDecimal()) or is too large for a finite `float`.
     */
    public static function toFloat(string $value): ?float
    {
        if (!AmountParser::isDecimal(value: $value)) {
            return null;
        }

        $result = (float) AmountParser::trim(value: $value);

        return is_finite(num: $result) ? $result : null;
    }

    /**
     * Returns the canonical decimal string with exactly `$scale` decimals (`'12'` becomes `'12.50'` for scale 2, no
     * sign for zero, no leading zeros), calculated with bcmath so there is no float rounding and no size limit.
     * Returns `null` if the value is not a decimal (see isDecimal()) or has more significant decimals than `$scale`: it
     * is never rounded. Trailing zeros do not change the amount and are accepted (`'12.500'` gives `'12.50'`).
     *
     * @param int $scale Number of decimals, 0 or more.
     */
    public static function toDecimal(string $value, int $scale): ?string
    {
        if (!AmountParser::isDecimal(value: $value)) {
            return null;
        }

        $trimmed = AmountParser::trim(value: $value);
        $decimalPoint = strpos(haystack: $trimmed, needle: '.');
        $decimals = $decimalPoint === false
            ? 0
            : strlen(string: rtrim(string: substr(string: $trimmed, offset: $decimalPoint + 1), characters: '0'));
        if ($decimals > $scale) {
            return null;
        }

        // @phpstan-ignore argument.type (isDecimal() guarantees a numeric string, PHPStan cannot see it)
        return bcadd(num1: $trimmed, num2: '0', scale: $scale);
    }

    private static function trim(string $value): string
    {
        return trim(string: $value, characters: AmountParser::WHITESPACE);
    }
}
