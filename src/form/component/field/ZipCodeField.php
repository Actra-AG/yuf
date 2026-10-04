<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\datacheck\validatorTypes\ZipCodeValidator;
use actra\yuf\form\FormInput;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\html\HtmlText;

/**
 * A text field for a zip code, checked against the format of its country (`ZipCodeValidator`). The country code can
 * be posted with the field (a country select named `countryCodeFieldName`); manipulated input is ignored.
 */
final class ZipCodeField extends TextField
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value = null,
        ?HtmlText $requiredError = null,
        private readonly ?HtmlText $individualInvalidError = null,
        private(set) string $countryCode = 'CH',
        private readonly string $countryCodeFieldName = 'countryCode',
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null,
        ?int $maxLength = null
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            value: $value,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength
        );
    }

    protected function readAdditionalInput(FormInput $input): void
    {
        // Only text is accepted: manipulated (array) input is ignored, the current country code stays.
        $countryCode = $input->getText(name: $this->countryCodeFieldName);
        if ($countryCode !== null) {
            $this->countryCode = $countryCode;
        }
    }

    public function validateCurrentValue(): bool
    {
        if (
            !$this->isValueEmpty()
            && !ZipCodeValidator::validate(zipCode: $this->getValueAsString(), countryCode: $this->countryCode)
        ) {
            $this->addInvalidZipCodeError();
        }

        return parent::validateCurrentValue();
    }

    private function addInvalidZipCodeError(): void
    {
        if ($this->individualInvalidError === null) {
            $this->addError(errorMessage: HtmlText::unencoded(textContent: $this->messages->invalidZipCode));

            return;
        }
        $this->addError(errorMessage: $this->individualInvalidError);
    }
}