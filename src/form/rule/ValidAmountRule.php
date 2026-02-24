<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

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
        if (!is_numeric($value)) {
            return false;
        }

        if (!$this->valueIsFloat && is_float($value)) {
            return false;
        }

        return true;
    }
}