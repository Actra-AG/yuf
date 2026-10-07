<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\component\field\TextAreaField;

/**
 * A subclass that fills the field like a project would, with the protected `setInitialValue()`.
 */
final class InitialValueTextAreaField extends TextAreaField
{
    public function fill(string $value): void
    {
        $this->setInitialValue(value: $value);
    }
}
