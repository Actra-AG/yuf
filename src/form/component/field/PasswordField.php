<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\rule\RequiredRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;

class PasswordField extends InputField
{
    public function __construct(
        string $name,
        HtmlText $label,
        HtmlText $requiredError,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null,
        ?int $maxLength = null
    ) {
        parent::__construct(
            inputType: InputTypeValue::PASSWORD,
            name: $name,
            label: $label,
            value: '',
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength
        );
        $this->addRule(formRule: new RequiredRule(defaultErrorMessage: $requiredError));
    }
}