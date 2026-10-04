<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\AmountParser;
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
            return $this->valueIsFloat && is_finite(num: $value);
        }
        if (!is_string(value: $value)) {
            return false;
        }

        // Surrounding whitespace is accepted like is_numeric() does: AmountField trims its input, other fields using
        // this rule (HiddenField) may not. Values out of the int/float range are invalid, so the numeric getters
        // never fail after a successful validation.
        return $this->valueIsFloat
            ? AmountParser::toFloat(value: $value) !== null
            : AmountParser::toInt(value: $value) !== null;
    }
}