<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck\sanitizerTypes;

use RuntimeException;

/**
 * Turns a number as entered into a float: `.` or `,` as decimal separator (not both), optional minus sign,
 * optional exponent (`1.5E2`). Nothing else is accepted: no plus sign, no thousands separator, no text. The
 * conversion does not depend on the locale of the process. A text that is not zero but would become zero (`1E-400`)
 * or infinite (`1E400`) is rejected, because that is a sign of a typing mistake.
 */
final readonly class FloatSanitizer
{
    private const string ERROR_MESSAGE = 'Value is not suitable as FLOAT.';

    /**
     * @throws RuntimeException if the input is no number, or does not fit into a float
     */
    public static function sanitize(float|int|string $input): float
    {
        if (is_float(value: $input)) {
            return $input;
        }
        if (is_int(value: $input)) {
            return (float) $input;
        }
        $parts = explode(separator: 'E', string: strtoupper(string: trim(string: $input)));
        if (count(value: $parts) > 2) {
            throw new RuntimeException(message: FloatSanitizer::ERROR_MESSAGE);
        }
        $base = $parts[0];
        $exponent = count(value: $parts) === 2 ? $parts[1] : null;
        // Digits are required: '-', '.', ',' and 'E5' are no numbers
        if (preg_match(pattern: '/^-?(\d+[.,]?\d*|[.,]\d+)$/D', subject: $base) !== 1) {
            throw new RuntimeException(message: FloatSanitizer::ERROR_MESSAGE);
        }
        if ($exponent !== null && preg_match(pattern: '/^-?\d+$/D', subject: $exponent) !== 1) {
            throw new RuntimeException(message: FloatSanitizer::ERROR_MESSAGE);
        }
        // A base of zeros is zero for sure, whatever the exponent says
        if (preg_match(pattern: '/^-?0*[.,]?0*$/D', subject: $base) === 1) {
            return 0.0;
        }
        $normalized = str_replace(search: ',', replace: '.', subject: $base)
            . ($exponent === null ? '' : 'E' . $exponent);
        $value = (float) $normalized;
        if ($value === 0.0 || is_infinite(num: $value)) {
            throw new RuntimeException(message: FloatSanitizer::ERROR_MESSAGE);
        }

        return $value;
    }
}
