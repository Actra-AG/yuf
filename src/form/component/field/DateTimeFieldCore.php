<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use DateTimeImmutable;
use actra\yuf\datacheck\Sanitizer;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use Throwable;

abstract class DateTimeFieldCore extends InputField
{
    public function __construct(
        InputTypeValue $inputType,
        string $name,
        HtmlText $label,
        private readonly string $renderValueFormat,
        ?string $value = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null
    ) {
        parent::__construct(
            inputType: $inputType,
            name: $name,
            label: $label,
            value: $value,
            placeholder: $placeholder,
            autoComplete: $autoComplete
        );
        if (!is_null(value: $requiredError)) {
            $this->addRule(formRule: new RequiredRule(defaultErrorMessage: $requiredError));
        }
    }

    public function renderValue(): string
    {
        if ($this->isValueEmpty()) {
            return '';
        }
        $originalValue = $this->getRawValue();
        if ($this->hasErrors(withChildElements: false)) {
            // Invalid value; show original input
            return HtmlEncoder::encode(value: Sanitizer::trimmedString(input: $originalValue));
        }
        try {
            return new DateTimeImmutable(datetime: $originalValue)->format(format: $this->renderValueFormat);
        } catch (Throwable) {
            // Should not be reached. Anyway ... invalid value; show original input
            return HtmlEncoder::encode(value: Sanitizer::trimmedString(input: $originalValue));
        }
    }
}