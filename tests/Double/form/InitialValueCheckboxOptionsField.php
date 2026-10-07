<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\component\field\CheckboxOptionsField;

/**
 * A subclass that fills the field like a project would, with the protected `setInitialValues()`.
 */
final class InitialValueCheckboxOptionsField extends CheckboxOptionsField
{
    /**
     * @param list<string> $values
     */
    public function fill(array $values): void
    {
        $this->setInitialValues(values: $values);
    }
}
