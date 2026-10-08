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
 * Turns template values into text and escapes them (design section 5).
 *
 * The values are open (`mixed`): arrays and objects are rejected here, so no caller has to check the type.
 *
 * @internal
 */
final readonly class ValueFormatter
{
    /**
     * The value as HTML: `TrustedHtml` as it is, every other string, number and `Stringable` escaped, `null` as an
     * empty string and a boolean as `'1'` or `''`.
     */
    public function escape(mixed $value): string
    {
        if ($value instanceof TrustedHtml) {
            return $value->html;
        }

        return htmlspecialchars(
            string: $this->text(value: $value),
            flags: ENT_QUOTES | ENT_SUBSTITUTE,
            encoding: 'UTF-8',
        );
    }

    /**
     * The value as unescaped text. Arrays and objects (except `Stringable` and `TrustedHtml`) are an error.
     */
    public function text(mixed $value): string
    {
        return match (true) {
            $value === null => '',
            is_bool(value: $value) => $value ? '1' : '',
            is_string(value: $value) => $value,
            is_int(value: $value), is_float(value: $value) => (string) $value,
            $value instanceof TrustedHtml => $value->html,
            $value instanceof Stringable => (string) $value,
            default => throw new TemplateException(
                reason: 'Cannot output a value of type ' . get_debug_type(value: $value)
                    . ', only text, numbers, booleans and null',
            ),
        };
    }
}
