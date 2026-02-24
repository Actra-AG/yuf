<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\component\field;

use actra\yuf\datacheck\Sanitizer;
use actra\yuf\form\rule\PhoneNumberRule;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneParseException;
use actra\yuf\phone\PhoneRenderer;

class PhoneNumberField extends InputField
{
    private(set) string $countryCode;

    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value,
        HtmlText $invalidErrorMessage,
        ?HtmlText $requiredErrorMessage = null,
        string $countryCode = 'CH',
        public readonly string $countryCodeFieldName = 'countryCode',
        public readonly bool $renderInternalFormat = false,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null
    ) {
        parent::__construct(
            inputType: InputTypeValue::TEL,
            name: $name,
            label: $label,
            value: $value,
            placeholder: $placeholder,
            autoComplete: $autoComplete
        );
        $this->countryCode = $countryCode;
        if (!is_null(value: $requiredErrorMessage)) {
            $this->addRule(formRule: new RequiredRule(defaultErrorMessage: $requiredErrorMessage));
        }
        $this->addRule(formRule: new PhoneNumberRule(defaultErrorMessage: $invalidErrorMessage));
    }

    public function validate(array $inputData, bool $overwriteValue = true): bool
    {
        if (array_key_exists(key: $this->countryCodeFieldName, array: $inputData)) {
            $this->countryCode = $inputData[$this->countryCodeFieldName];
        }

        return parent::validate(inputData: $inputData, overwriteValue: $overwriteValue);
    }

    public function renderValue(): string
    {
        if ($this->isValueEmpty()) {
            return '';
        }
        $currentValue = $this->getRawValue();
        if ($this->hasErrors(withChildElements: true)) {
            return $currentValue;
        }
        try {
            $phoneNumber = PhoneNumber::createFromString(
                input: $currentValue,
                defaultCountryCode: $this->countryCode
            );
        } catch (PhoneParseException) {
            return HtmlEncoder::encode(value: $currentValue);
        }
        if ($this->renderInternalFormat) {
            return PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber);
        }

        return PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber);
    }

    public function valueHasChanged(): bool
    {
        $originalValue = Sanitizer::trimmedString(input: $this->getOriginalValue());
        if ($originalValue !== '') {
            try {
                $originalValue = PhoneRenderer::renderInternalFormat(
                    phoneNumber: PhoneNumber::createFromString(
                        input: $this->getOriginalValue(),
                        defaultCountryCode: $this->countryCode
                    )
                );
            } catch (PhoneParseException) {
                $originalValue = '';
            }
        }

        return ($this->getRawValue() !== $originalValue);
    }
}