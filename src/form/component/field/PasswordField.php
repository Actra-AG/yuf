<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\rule\MinLengthRule;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\form\settings\PasswordPurposeEnum;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use Override;

/**
 * A password is read exactly as typed (no normalization), has no setter and no initial value, and is never rendered
 * back into the HTML.
 */
final class PasswordField extends StringInputField
{
    private ?int $minLength = null;
    private ?HtmlText $minLengthError = null;

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

    /**
     * Requires a minimum length (in characters) of a password that is entered; an empty password is the business of
     * the required rule. Call it for fields that set a password (`PasswordPurposeEnum::NEW`), not for a login: a
     * minimum length there would lock out users whose password predates the rule.
     *
     * @param ?HtmlText $errorMessage Default: `FormMessages::$passwordTooShort` of the form with `[min]` replaced
     * @throws InvalidArgumentException If the minimum length is less than 1.
     */
    public function setMinLength(int $minLength, ?HtmlText $errorMessage = null): void
    {
        if ($minLength < 1) {
            throw new InvalidArgumentException(
                message: 'The minimum length of field ' . $this->name . ' must be at least 1, ' . $minLength
                . ' given.',
            );
        }
        $this->minLength = $minLength;
        $this->minLengthError = $errorMessage;
    }

    /**
     * The minimum length is a rule (`MinLengthRule`) whose default message is only known when the field is part of a
     * form, so the rule is created here.
     */
    #[Override]
    protected function checkRules(): void
    {
        parent::checkRules();
        if ($this->minLength === null || $this->isValueEmpty()) {
            return;
        }
        $rule = new MinLengthRule(
            minLength: $this->minLength,
            errorMessage: $this->minLengthError ?? HtmlText::fromText(
                text: str_replace(
                    search: '[min]',
                    replace: (string) $this->minLength,
                    subject: $this->messages->passwordTooShort,
                ),
            ),
        );
        if (!$rule->validate(value: $this->getValueAsString())) {
            $this->addError(errorMessage: $rule->getErrorMessage());
        }
    }

    #[Override]
    protected function normalize(string $input): string
    {
        return $input;
    }

    /**
     * A password is never rendered back into the HTML (e.g. when the form is shown again with validation errors),
     * so it cannot end up in the page source, browser caches or logs.
     */
    #[Override]
    public function renderValue(): string
    {
        return '';
    }
}
