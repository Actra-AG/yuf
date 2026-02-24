<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormComponent;

class NullField extends FormComponent
{
    public function render(): string
    {
        return '';
    }
}