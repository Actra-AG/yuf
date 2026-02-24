<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\RadioOptionsField;

class RadioOptionsRenderer extends DefaultOptionsRenderer
{
    public function __construct(RadioOptionsField $radioOptionsField)
    {
        parent::__construct(optionsField: $radioOptionsField, inputFieldType: 'radio', acceptMultipleValues: false);
    }
}