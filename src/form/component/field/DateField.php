<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\rule\ValidDateRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;
use DateTimeImmutable;

class DateField extends DateTimeFieldCore
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
            inputType: InputTypeValue::DATE,
            name: $name,
            label: $label,
            renderValueFormat: 'Y-m-d',
            value: $value,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete
        );
        $this->addRule(formRule: new ValidDateRule(defaultErrorMessage: $invalidError));
    }

    public function getValueAsDateTimeImmutable(): ?DateTimeImmutable
    {
        $rawValue = $this->getRawValue();

        return $rawValue === '' ? null : new DateTimeImmutable(datetime: $rawValue);
    }
}