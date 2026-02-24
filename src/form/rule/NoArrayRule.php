<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\rule;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRule;

class NoArrayRule extends FormRule
{
    public function validate(FormField $formField): bool
    {
        return !is_array($formField->getRawValue());
    }
}