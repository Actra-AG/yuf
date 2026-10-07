<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;

/**
 * A password is read exactly as typed (no normalization), has no setter and no initial value, and is never rendered
 * back into the HTML.
 */
final class PasswordField extends StringInputField
{
    public function __construct(
        string $name,
        HtmlText $label,
        HtmlText $requiredError,
        PasswordPurposeEnum $purpose,
        ?string $placeholder = null,
        ?int $maxLength = null,
    ) {
        parent::__construct(
            inputType: InputTypeEnum::PASSWORD,
            name: $name,
            label: $label,
            value: null,
            placeholder: $placeholder,
            autoComplete: $purpose->autoComplete(),
            maxLength: $maxLength,
        );
        $this->addRequiredRule(errorMessage: $requiredError);
    }

    protected function normalize(string $input): string
    {
        return $input;
    }

    /**
     * A password is never rendered back into the HTML (e.g. when the form is shown again with validation errors),
     * so it cannot end up in the page source, browser caches or logs.
     */
    public function renderValue(): string
    {
        return '';
    }
}
