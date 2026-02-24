<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component\field;

use actra\yuf\form\rule\ValidTimeRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;

class TimeField extends DateTimeFieldCore
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value,
        HtmlText $invalidError,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null
    ) {
        parent::__construct(
            inputType: InputTypeValue::TIME,
            name: $name,
            label: $label,
            renderValueFormat: 'H:i',
            value: $value,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete
        );
        $this->addRule(formRule: new ValidTimeRule(defaultErrorMessage: $invalidError));
    }
}