<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormComponent;
use Override;

final class NullField extends FormComponent
{
    #[Override]
    public function render(): string
    {
        return '';
    }
}
