<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\CheckboxOptionsField;

class CheckboxOptionsRenderer extends DefaultOptionsRenderer
{
    public function __construct(CheckboxOptionsField $checkboxOptionsField)
    {
        parent::__construct(
            optionsField: $checkboxOptionsField,
            inputFieldType: 'checkbox',
            acceptMultipleValues: true
        );
    }
}