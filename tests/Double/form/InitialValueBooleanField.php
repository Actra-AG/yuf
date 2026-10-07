<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\form;

use actra\yuf\form\component\field\BooleanField;

/**
 * A subclass that fills the field like a project would, with the protected `setInitiallyChecked()`.
 */
final class InitialValueBooleanField extends BooleanField
{
    public function fill(bool $checked): void
    {
        $this->setInitiallyChecked(checked: $checked);
    }
}
