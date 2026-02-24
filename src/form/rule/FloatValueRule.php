<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\rule;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;

class FloatValueRule extends FormRule
{
    public function validate(FormField $formField): bool
    {
        if ($formField->isValueEmpty()) {
            return true;
        }

        $value = $formField->getRawValue();

        return (
            filter_var($value, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_THOUSAND) !== false ||
            filter_var($value, FILTER_VALIDATE_FLOAT, [
                'flags' => FILTER_FLAG_ALLOW_THOUSAND,
                'options' => ['decimal' => ','],
            ]) !== false
        );
    }
}