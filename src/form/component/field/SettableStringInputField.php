<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use TypeError;

/**
 * A string input field with a public setter.
 */
abstract class SettableStringInputField extends StringInputField
{
    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     *
     * The parameter is declared `mixed` only while the legacy `FormField::setValue(mixed)` bridge exists (PHP does
     * not allow narrowing it); it becomes `string` with the removal of the bridge.
     *
     * @throws TypeError If the value is not a string.
     */
    public function setValue(mixed $value): void
    {
        if (!is_string(value: $value)) {
            throw new TypeError(
                message: 'The value of field ' . $this->name . ' must be a string, ' . get_debug_type(value: $value)
                . ' given.'
            );
        }
        $this->changeText(text: $value);
    }
}