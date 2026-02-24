<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\RadioOptionsField;

class RadioOptionsRenderer extends DefaultOptionsRenderer
{
    public function __construct(RadioOptionsField $radioOptionsField)
    {
        parent::__construct(optionsField: $radioOptionsField, inputFieldType: 'radio', acceptMultipleValues: false);
    }
}