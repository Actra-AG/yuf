<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormInput;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use actra\yuf\phone\PhoneNumber;
use actra\yuf\phone\PhoneNumberTypeEnum;
use actra\yuf\phone\PhoneParseException;
use actra\yuf\phone\PhoneRenderer;
use Override;

/**
 * A text field for a phone number. A valid number is stored in the internal format (`+41.446681800`), also when it is
 * set by the constructor or `setValue()`; it is rendered in the international format (`+41 44 668 18 00`) or, with
 * `renderInternalFormat`, in the internal format. A number without country code is read with the country code of the
 * field, which can be posted with the field (named `countryCodeFieldName`; manipulated input is ignored). An invalid
 * number stays as typed (trimmed) and adds `invalidErrorMessage` when the field is validated (a number is accepted
 * if it is valid for its country, not only possible by its length). With `allowedNumberTypes`, the number must also
 * be of one of the types (a number that fits fixed line and mobile numbers fits both types), otherwise
 * `numberTypeErrorMessage` (default: `invalidErrorMessage`) is added.
 */
final class PhoneNumberField extends SettableStringInputField
{
    public private(set) string $countryCode;

    /**
     * @param list<PhoneNumberTypeEnum> $allowedNumberTypes empty: every valid number is accepted
     */
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value,
        private readonly HtmlText $invalidErrorMessage,
        ?HtmlText $requiredErrorMessage = null,
        string $countryCode = 'CH',
        public readonly string $countryCodeFieldName = 'countryCode',
        public readonly bool $renderInternalFormat = false,
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null,
        public readonly array $allowedNumberTypes = [],
        private readonly ?HtmlText $numberTypeErrorMessage = null,
    ) {
        // The value is normalized in the parent constructor, which needs the country code.
        $this->countryCode = $countryCode;
        parent::__construct(
            inputType: InputTypeEnum::TEL,
            name: $name,
            label: $label,
            value: $value,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
        );
        if ($requiredErrorMessage !== null) {
            $this->addRequiredRule(errorMessage: $requiredErrorMessage);
        }
    }

    /**
     * A valid number is stored in the internal format, an invalid one stays as typed (trimmed), so the user can
     * correct it.
     */
    #[Override]
    protected function normalize(string $input): string
    {
        $text = parent::normalize(input: $input);
        $phoneNumber = $this->parsePhoneNumber(text: $text);

        return $phoneNumber === null ? $text : PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber);
    }

    #[Override]
    protected function readAdditionalInput(FormInput $input): void
    {
        // Only text is accepted: manipulated (array) input is ignored, the current country code stays.
        $countryCode = $input->getText(name: $this->countryCodeFieldName);
        if ($countryCode !== null) {
            $this->countryCode = $countryCode;
        }
    }

    #[Override]
    public function validateCurrentValue(): bool
    {
        if (!$this->isValueEmpty()) {
            $phoneNumber = $this->parsePhoneNumber(text: $this->getValueAsString());
            if ($phoneNumber === null) {
                $this->addError(errorMessage: $this->invalidErrorMessage);
            } elseif (!$this->isNumberTypeAllowed(phoneNumber: $phoneNumber)) {
                $this->addError(errorMessage: $this->numberTypeErrorMessage ?? $this->invalidErrorMessage);
            }
        }

        return parent::validateCurrentValue();
    }

    #[Override]
    public function renderValue(): string
    {
        if ($this->isValueEmpty()) {
            return '';
        }
        $text = $this->getValueAsString();
        $phoneNumber = $this->parsePhoneNumber(text: $text);
        if ($this->hasErrors(withChildElements: true) || $phoneNumber === null) {
            return HtmlEncoder::encode(value: $text);
        }
        if ($this->renderInternalFormat) {
            return PhoneRenderer::renderInternalFormat(phoneNumber: $phoneNumber);
        }

        return PhoneRenderer::renderInternationalFormat(phoneNumber: $phoneNumber);
    }

    private function isNumberTypeAllowed(PhoneNumber $phoneNumber): bool
    {
        if ($this->allowedNumberTypes === []) {
            return true;
        }
        foreach ($this->allowedNumberTypes as $numberType) {
            if ($phoneNumber->isValidForType(numberType: $numberType)) {
                return true;
            }
        }

        return false;
    }

    private function parsePhoneNumber(string $text): ?PhoneNumber
    {
        if ($text === '') {
            return null;
        }
        try {
            $phoneNumber = PhoneNumber::createFromString(input: $text, defaultCountryCode: $this->countryCode);
        } catch (PhoneParseException) {
            return null;
        }

        return $phoneNumber->isValid() ? $phoneNumber : null;
    }
}
