<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

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