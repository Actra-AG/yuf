<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\common\ValidatedEmailAddress;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\form\rule\ValidEmailAddressRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;

final class EmailField extends SettableStringInputField
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

    /**
     * A valid address is stored in its canonical form (lower case, no whitespace); an invalid one stays as typed
     * (trimmed), so the user can correct it.
     */
    protected function normalize(string $input): string
    {
        $text = parent::normalize(input: $input);
        $validatedEmailAddress = new ValidatedEmailAddress(emailAddress: $text);

        return $validatedEmailAddress->isValidSyntax ? $validatedEmailAddress->validatedValue : $text;
    }
}