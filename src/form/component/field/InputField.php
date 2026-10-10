<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\InputFieldRenderer;
use actra\yuf\form\rule\MaxLengthRule;
use actra\yuf\form\rule\StringRule;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use Override;

/**
 * An `input` element. `maxLength` is rendered as `maxlength` attribute and validated on the server (the text of the
 * field must not have more characters; message `FormMessages::$tooLong`). A `MaxLengthRule` of the project with the
 * same or a smaller limit replaces that check, so the user gets one error.
 */
abstract class InputField extends TextualField
{
    private bool $hasOwnMaxLengthRule = false;

    public function __construct(
        public readonly InputTypeEnum $inputType,
        string $name,
        HtmlText $label,
        public readonly ?string $placeholder,
        public readonly ?AutoCompleteEnum $autoComplete,
        public readonly ?int $maxLength = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
        );
    }

    #[Override]
    public function addRule(StringRule $formRule): void
    {
        parent::addRule(formRule: $formRule);
        if ($formRule instanceof MaxLengthRule && $formRule->maxLength <= ($this->maxLength ?? PHP_INT_MAX)) {
            $this->hasOwnMaxLengthRule = true;
        }
    }

    #[Override]
    protected function checkRules(): void
    {
        parent::checkRules();
        if ($this->maxLength === null || $this->hasOwnMaxLengthRule || $this->isValueEmpty()) {
            return;
        }
        $rule = new MaxLengthRule(
            maxLength: $this->maxLength,
            errorMessage: HtmlText::fromText(
                text: str_replace(
                    search: '[max]',
                    replace: (string) $this->maxLength,
                    subject: $this->messages->tooLong,
                ),
            ),
        );
        if (!$rule->validate(value: $this->getText())) {
            $this->addError(errorMessage: $rule->getErrorMessage());
        }
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new InputFieldRenderer(formField: $this);
    }
}
