<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\sanitizerTypes;

use RuntimeException;

/**
 * Turns a number as entered into an int: only whole numbers within the range of PHP integers. Strings with digits
 * are checked without a loss of precision; other numeric strings (`1e3`, `1.0`) go through `FloatSanitizer`.
 */
final readonly class IntegerSanitizer
{
    /** The float `PHP_INT_MAX` is 2^63, which is one more than the largest int: floats must be below it */
    private const float INT_RANGE_END = 9.2233720368547758E+18;

    /**
     * @throws RuntimeException if the input is no number, no whole number, or out of range
     */
    public static function sanitize(int|float|string $input): int
    {
        if (is_int(value: $input)) {
            return $input;
        }
        if (!is_numeric(value: $input)) {
            throw new RuntimeException(message: 'Value is not suitable as INT.');
        }
        if (is_string(value: $input)) {
            $trimmed = trim(string: $input);
            // An INT contains only digits, but might have an - in front of it
            if (is_numeric(value: $trimmed) && preg_match(pattern: '/^-?\d+$/D', subject: $trimmed) === 1) {
                return IntegerSanitizer::convertDigits(digits: $trimmed);
            }
            // Maybe it's a "stringed FLOAT"?
            try {
                $input = FloatSanitizer::sanitize(input: $trimmed);
            } catch (RuntimeException) {
                throw new RuntimeException(message: 'Value is not suitable as INT.');
            }
        }

        return IntegerSanitizer::convertFloat(value: $input);
    }

    /**
     * @param numeric-string $digits
     *
     * @throws RuntimeException if the number is out of range
     */
    private static function convertDigits(string $digits): int
    {
        if (
            bccomp(num1: $digits, num2: (string) PHP_INT_MAX) === 1
            || bccomp(num1: $digits, num2: (string) PHP_INT_MIN) === -1
        ) {
            throw new RuntimeException(message: 'Value is out of range as INT.');
        }

        return (int) $digits;
    }

    /**
     * @throws RuntimeException if the number is out of range or has a fraction
     */
    private static function convertFloat(float $value): int
    {
        if ($value >= IntegerSanitizer::INT_RANGE_END || $value < -IntegerSanitizer::INT_RANGE_END) {
            throw new RuntimeException(message: 'Value is out of range as INT.');
        }
        if (fmod(num1: $value, num2: 1.0) !== 0.0) {
            throw new RuntimeException(message: 'Value is not a whole number.');
        }

        return (int) $value;
    }
}
