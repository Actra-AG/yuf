<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;

class NumericValueRule extends FormRule
{
    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        return is_numeric($formField->getRawValue());
    }
}