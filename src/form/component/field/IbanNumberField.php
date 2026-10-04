<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\datacheck\validatorTypes\IbanValidator;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlText;

/**
 * A text field for an international bank account number (`IbanValidator`). The value is stored as typed (trimmed,
 * spaces and case stay).
 */
final class IbanNumberField extends TextField
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value,
        private readonly HtmlText $invalidError,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            value: $value,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete
        );
    }

    /**
     * The IBAN is only checked if the other rules passed, so a field has one error at a time.
     */
    public function validateCurrentValue(): bool
    {
        if (!parent::validateCurrentValue()) {
            return false;
        }
        if ($this->isValueEmpty() || IbanValidator::validate(input: $this->getValueAsString())) {
            return true;
        }
        $this->addErrorAsHtmlTextObject(errorMessageObject: $this->invalidError);

        return false;
    }
}