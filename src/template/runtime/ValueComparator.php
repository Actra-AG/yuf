<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\runtime;

use actra\yuf\template\TemplateException;
use Stringable;

/**
 * The comparison of the `if` tag (design section 3.1). It does not use PHP's loose comparison. The compared value is
 * open (`mixed`): it comes from the template data.
 *
 * @internal
 */
final readonly class ValueComparator
{
    /**
     * @throws TemplateException for `gt`, `ge`, `lt` and `le` with a value or an `against` that is not numeric
     */
    public function compare(
        mixed $value,
        ComparisonOperatorEnum $operator,
        string $against,
    ): bool {
        $value = $value instanceof TrustedHtml ? $value->html : $value;

        return match ($operator) {
            ComparisonOperatorEnum::EQ => $this->isEqual(value: $value, against: $against),
            ComparisonOperatorEnum::NE => !$this->isEqual(value: $value, against: $against),
            ComparisonOperatorEnum::IN => $this->isIn(value: $value, against: $against),
            ComparisonOperatorEnum::GT => $this->compareNumbers(value: $value, against: $against) > 0,
            ComparisonOperatorEnum::GE => $this->compareNumbers(value: $value, against: $against) >= 0,
            ComparisonOperatorEnum::LT => $this->compareNumbers(value: $value, against: $against) < 0,
            ComparisonOperatorEnum::LE => $this->compareNumbers(value: $value, against: $against) <= 0,
        };
    }

    private function isEqual(mixed $value, string $against): bool
    {
        return match (strtolower(string: $against)) {
            'null' => $value === null || $value === '' || $value === [] || $value === false || $value === 0 || $value === 0.0,
            '' => $value === null || $value === '' || $value === false,
            'true' => $this->isTruthy(value: $value),
            'false' => !$this->isTruthy(value: $value),
            default => $this->equalsAsText(value: $value, against: $against),
        };
    }

    private function isTruthy(mixed $value): bool
    {
        return $value instanceof Stringable ? (string) $value !== '' && (string) $value !== '0' : (bool) $value;
    }

    /**
     * Only strings, numbers and `Stringable` have a text to compare; the keywords `null`, `true` and `false` are not
     * special here.
     */
    private function equalsAsText(mixed $value, string $against): bool
    {
        if (is_string(value: $value) || is_int(value: $value) || is_float(value: $value) || $value instanceof Stringable) {
            return (string) $value === $against;
        }

        return false;
    }

    private function isIn(mixed $value, string $against): bool
    {
        foreach (explode(separator: ' ', string: $against) as $item) {
            if ($this->equalsAsText(value: $value, against: $item)) {
                return true;
            }
        }

        return false;
    }

    private function compareNumbers(mixed $value, string $against): int
    {
        if (!(is_int(value: $value) || is_float(value: $value) || is_string(value: $value)) || !is_numeric(value: $value)) {
            throw new TemplateException(
                reason: 'The operators gt, ge, lt and le need a numeric value, got ' . get_debug_type(value: $value),
            );
        }
        if (!is_numeric(value: $against)) {
            throw new TemplateException(
                reason: 'The operators gt, ge, lt and le need a numeric against attribute, got "' . $against . '"',
            );
        }

        return $value + 0 <=> $against + 0;
    }
}
