<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

/**
 * A string input field with a public setter.
 */
abstract class SettableStringInputField extends StringInputField
{
    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     */
    public function setValue(string $value): void
    {
        $this->changeText(text: $value);
    }
}