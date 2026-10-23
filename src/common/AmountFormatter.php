<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use InvalidArgumentException;
use RoundingMode;

/**
 * Formats amounts with a thousands separator and a decimal separator for output (`27839.32` becomes `27'839.32` with
 * `swiss()`). Decimals are formatted from the decimal string (like the values of `DecimalField`), calculated with
 * bcmath: no float rounding, no size limit. Without currency: the caller adds it (`'CHF ' . $formatted`).
 */
final readonly class AmountFormatter
{
    /**
     * @param string $thousandsSeparator Between groups of three digits, `''` for no grouping.
     * @param string $decimalSeparator Between the integer part and the decimals.
     * @throws InvalidArgumentException If the decimal separator is empty or equals the thousands separator.
     */
    public function __construct(public string $thousandsSeparator, public string $decimalSeparator)
    {
        if ($decimalSeparator === '') {
            throw new InvalidArgumentException(message: 'The decimal separator must not be empty.');
        }
        if ($decimalSeparator === $thousandsSeparator) {
            throw new InvalidArgumentException(
                message: 'The decimal separator must differ from the thousands separator, both are "'
                . $decimalSeparator . '".',
            );
        }
    }

    /**
     * `1'234.50`, with the ASCII apostrophe (for the typographic `’`, use the constructor).
     */
    public static function swiss(): AmountFormatter
    {
        return new AmountFormatter(thousandsSeparator: '\'', decimalSeparator: '.');
    }

    /**
     * `1.234,50`
     */
    public static function german(): AmountFormatter
    {
        return new AmountFormatter(thousandsSeparator: '.', decimalSeparator: ',');
    }

    /**
     * `1,234.50`
     */
    public static function english(): AmountFormatter
    {
        return new AmountFormatter(thousandsSeparator: ',', decimalSeparator: '.');
    }

    /**
     * Formats a decimal string (optional minus sign, digits, optionally a dot and digits: `-2500.50`). Leading zeros are
     * removed and zero has no sign.
     *
     * @param ?int $scale The number of decimals, 0 or more: pads with zeros or rounds half away from zero (`0.005`
     *     gives `0.01`). `null` keeps the decimals of the amount.
     * @throws InvalidArgumentException If the amount is no decimal string or the scale is negative.
     */
    public function formatDecimal(string $amount, ?int $scale = null): string
    {
        // is_numeric() lets PHPStan know the numeric string, the pattern is stricter.
        if (
            !is_numeric(value: $amount)
            || preg_match(pattern: '/^-?\d+(?:\.(\d+))?$/D', subject: $amount, matches: $matches) !== 1
        ) {
            throw new InvalidArgumentException(message: 'The amount "' . $amount . '" is no decimal string.');
        }
        if ($scale !== null && $scale < 0) {
            throw new InvalidArgumentException(message: 'The scale must not be negative, ' . $scale . ' given.');
        }
        // bcmath normalizes: no leading zeros, no sign for zero.
        $normalized = $scale === null
            ? bcadd(
                num1: $amount,
                num2: '0',
                scale: array_key_exists(key: 1, array: $matches) ? strlen(string: $matches[1]) : 0,
            )
            : bcround(num: $amount, precision: $scale, mode: RoundingMode::HalfAwayFromZero);

        return $this->formatNormalized(amount: $normalized);
    }

    public function formatInt(int $amount): string
    {
        return $this->formatNormalized(amount: (string) $amount);
    }

    /**
     * @param string $amount Optional minus sign, digits without leading zeros, optionally a dot and digits.
     */
    private function formatNormalized(string $amount): string
    {
        $sign = str_starts_with(haystack: $amount, needle: '-') ? '-' : '';
        $parts = explode(separator: '.', string: ltrim(string: $amount, characters: '-'), limit: 2);
        // Groups of three digits from the right, e.g. 1234567 gives 1, 234, 567.
        $integer = implode(
            separator: $this->thousandsSeparator,
            array: array_map(
                callback: strrev(...),
                array: array_reverse(array: str_split(string: strrev(string: $parts[0]), length: 3)),
            ),
        );

        return $sign . $integer . (array_key_exists(key: 1, array: $parts) ? $this->decimalSeparator . $parts[1] : '');
    }
}
