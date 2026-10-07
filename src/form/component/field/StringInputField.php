<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * An input field with a string value. It has no public setter (see SettableStringInputField).
 */
abstract class StringInputField extends InputField
{
    public function __construct(
        InputTypeEnum $inputType,
        string $name,
        HtmlText $label,
        ?string $value,
        ?string $placeholder,
        ?AutoCompleteEnum $autoComplete,
        ?int $maxLength = null,
    ) {
        parent::__construct(
            inputType: $inputType,
            name: $name,
            label: $label,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength,
        );
        if ($value !== null) {
            $this->changeInitialText(text: $value);
        }
    }

    /**
     * Returns the stored (normalized) value, without HTML encoding. `''` if the field is empty.
     */
    public function getValueAsString(): string
    {
        return $this->getText();
    }

    /**
     * Sets the current and the initial value. For subclasses that fill the field after `parent::__construct()`.
     *
     * @throws LogicException If the field has already been validated.
     */
    protected function setInitialValue(string $value): void
    {
        $this->changeInitialText(text: $value);
    }
}
