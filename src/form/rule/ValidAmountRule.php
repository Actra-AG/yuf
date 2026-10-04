<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;
use actra\yuf\html\HtmlText;

class ValidAmountRule extends FormRule
{
    private bool $valueIsFloat;

    public function __construct(bool $valueIsFloat, HtmlText $errorMessage)
    {
        $this->valueIsFloat = $valueIsFloat;

        parent::__construct($errorMessage);
    }

    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        $value = $formField->getRawValue();
        if (is_int(value: $value)) {
            return true;
        }
        if (is_float(value: $value)) {
            return $this->valueIsFloat;
        }
        if (!is_string(value: $value)) {
            return false;
        }

        // The stored value is not trimmed, so surrounding whitespace is accepted like is_numeric() does.
        $pattern = $this->valueIsFloat ? '/^[+-]?(\d+(\.\d*)?|\.\d+)$/' : '/^[+-]?\d+$/';

        return preg_match(
            pattern: $pattern,
            subject: trim(string: $value, characters: " \t\n\r\v\f")
        ) === 1;
    }
}