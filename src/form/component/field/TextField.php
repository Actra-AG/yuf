<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;

/**
 * A text input.
 *
 * Extension point: a project extends it for fields with a fixed meaning (name, website, search query) and sets
 * its rules, label and placeholder in the constructor. `ZipCodeField` and `IbanNumberField` are examples.
 */
class TextField extends SettableStringInputField
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null,
        ?int $maxLength = null,
    ) {
        parent::__construct(
            inputType: InputTypeEnum::TEXT,
            name: $name,
            label: $label,
            value: $value,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength,
        );
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
    }
}
