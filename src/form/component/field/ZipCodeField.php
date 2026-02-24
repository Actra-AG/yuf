<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\rule\ZipCodeRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlText;

class ZipCodeField extends TextField
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value = null,
        ?HtmlText $requiredError = null,
        ?HtmlText $individualInvalidError = null,
        private(set) string $countryCode = 'CH',
        private readonly string $countryCodeFieldName = 'countryCode',
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null,
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
        $invalidError = is_null(value: $individualInvalidError) ? HtmlText::encoded(
            textContent: 'Die eingegebene PLZ ist ungültig.'
        ) : $individualInvalidError;
        $this->addRule(formRule: new ZipCodeRule(defaultErrorMessage: $invalidError));
    }

    public function validate(array $inputData, bool $overwriteValue = true): bool
    {
        if (array_key_exists(key: $this->countryCodeFieldName, array: $inputData)) {
            $this->countryCode = $inputData[$this->countryCodeFieldName];
        }
        if (!parent::validate(inputData: $inputData, overwriteValue: $overwriteValue)) {
            return false;
        }

        return true;
    }
}