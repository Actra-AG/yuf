<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component\field;

use actra\yuf\form\rule\RequiredRule;
use actra\yuf\form\rule\ValidEmailAddressRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;

class EmailField extends InputField
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value,
        HtmlText $invalidError,
        ?HtmlText $requiredError = null,
        bool $dnsCheck = true,
        bool $trueOnDnsError = true,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null,
        ?int $maxLength = null
    ) {
        parent::__construct(
            inputType: InputTypeValue::EMAIL,
            name: $name,
            label: $label,
            value: $value,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength
        );
        if (!is_null(value: $requiredError)) {
            $this->addRule(formRule: new RequiredRule(defaultErrorMessage: $requiredError));
        }
        $this->addRule(
            formRule: new ValidEmailAddressRule(
                errorMessage: $invalidError,
                dnsCheck: $dnsCheck,
                trueOnDnsError: $trueOnDnsError
            )
        );
    }
}