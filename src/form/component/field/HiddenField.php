<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component\field;

use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;

class HiddenField extends InputField
{
    public function __construct(
        string $name,
        int|float|string|bool|null $value = null
    ) {
        parent::__construct(
            inputType: InputTypeValue::HIDDEN,
            name: $name,
            label: HtmlText::encoded(textContent: ''),
            value: $value,
            placeholder: null,
            autoComplete: null
        );
        $this->setRenderer(renderer: new HiddenFieldRenderer(hiddenField: $this));
    }
}