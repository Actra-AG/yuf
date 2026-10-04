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

class TextField extends SettableStringInputField
{
    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null,
        ?int $maxLength = null
    ) {
        parent::__construct(
            inputType: InputTypeEnum::TEXT,
            name: $name,
            label: $label,
            value: $value,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength
        );
        if (!is_null(value: $requiredError)) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
    }
}