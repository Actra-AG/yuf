<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\FormRule;
use actra\yuf\form\AmountParser;
use InvalidArgumentException;

/**
 * A rule for the value of a `DecimalField` (a canonical decimal string such as `'12.50'`). Called for a parsed,
 * non-empty value only; a pure predicate.
 */
abstract class DecimalRule extends FormRule
{
    abstract public function validate(string $value): bool;

    /**
     * Compares two decimal strings without float rounding.
     *
     * @return int `-1`, `0` or `1`, like `bccomp()`
     * @throws InvalidArgumentException If a value is not a decimal string.
     */
    final protected static function compare(string $left, string $right): int
    {
        if (!is_numeric(value: $left) || !is_numeric(value: $right)) {
            throw new InvalidArgumentException(
                message: 'Only decimal strings can be compared, "' . $left . '" and "' . $right . '" given.'
            );
        }

        return bccomp(
            num1: $left,
            num2: $right,
            scale: max(DecimalRule::countDecimals(decimal: $left), DecimalRule::countDecimals(decimal: $right))
        );
    }

    /**
     * Checks and canonicalizes the limit of a rule (e.g. `'+0.50'` becomes `'0.50'`).
     *
     * @throws InvalidArgumentException If the limit is not a decimal string.
     */
    final protected static function toLimit(string $decimal): string
    {
        $limit = AmountParser::toDecimal(
            value: $decimal,
            scale: DecimalRule::countDecimals(decimal: trim(string: $decimal))
        );
        if ($limit === null) {
            throw new InvalidArgumentException(
                message: 'The limit of a decimal rule must be a decimal string, "' . $decimal . '" given.'
            );
        }

        return $limit;
    }

    private static function countDecimals(string $decimal): int
    {
        $decimalPoint = strpos(haystack: $decimal, needle: '.');

        return $decimalPoint === false ? 0 : strlen(string: $decimal) - $decimalPoint - 1;
    }
}