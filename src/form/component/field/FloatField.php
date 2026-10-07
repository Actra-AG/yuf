<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\AmountParser;
use actra\yuf\form\FormFieldValueMissingException;
use actra\yuf\form\rule\FloatRule;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use Override;
use UnexpectedValueException;

/**
 * A decimal number as `?float` (`null` if empty), for measurements, not for money (use `DecimalField`): optional
 * sign, digits with an optional decimal point (`5`, `1.5`, `1.`, `.5`), surrounding whitespace is ignored, no exponent,
 * no value too large for a finite `float`. Input that is not a decimal is kept for re-rendering and gives the field's
 * error. It renders the canonical text of the value (`'1.50'` becomes `1.5`).
 */
final class FloatField extends ParsedInputField
{
    private ?float $value = null;
    /** @var list<FloatRule> */
    private array $valueRules = [];

    /**
     * @throws InvalidArgumentException If the initial value is `INF` or `NAN`.
     */
    public function __construct(
        string $name,
        HtmlText $label,
        ?float $initialValue = null,
        ?HtmlText $individualInvalidError = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null,
        ?int $maxLength = null,
    ) {
        parent::__construct(
            inputType: InputTypeEnum::TEXT,
            name: $name,
            label: $label,
            invalidError: $individualInvalidError,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength,
        );
        if ($initialValue !== null) {
            $this->changeInitialText(text: $this->toText(value: $initialValue));
        }
    }

    #[Override]
    protected function accept(string $text): void
    {
        $this->value = AmountParser::toFloat(value: $text);
        parent::accept(text: $this->value === null ? $text : $this->toText(value: $this->value));
    }

    #[Override]
    protected function hasParsedValue(): bool
    {
        return $this->value !== null;
    }

    /**
     * Adds a rule for the value (e.g. `FloatMinRule`). Value rules run for a parsed, non-empty value only.
     */
    public function addValueRule(FloatRule $formRule): void
    {
        $this->valueRules[] = $formRule;
    }

    #[Override]
    protected function checkRules(): void
    {
        parent::checkRules();
        if ($this->value === null) {
            return;
        }
        foreach ($this->valueRules as $rule) {
            if (!$rule->validate(value: $this->value)) {
                $this->addError(errorMessage: $rule->getErrorMessage());
            }
        }
    }

    /**
     * Returns the value, or `null` if the field is empty.
     *
     * @throws UnexpectedValueException If the field holds input that is not a decimal number (before validation or
     *         after a failed validation).
     */
    public function getValueAsFloat(): ?float
    {
        $this->assertValueCanBeRead(type: 'float');

        return $this->value;
    }

    /**
     * Returns the value of a required field. Use it after a successful `validate()`, the nullable getter for an
     * optional field.
     *
     * @throws FormFieldValueMissingException If the field is empty (not validated yet, or not required).
     * @throws UnexpectedValueException If the field holds input that is not valid.
     */
    public function getRequiredValueAsFloat(): float
    {
        return $this->getValueAsFloat() ?? throw $this->valueMissing(nullableGetter: 'getValueAsFloat');
    }

    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     *
     * @throws InvalidArgumentException If the value is `INF` or `NAN`.
     */
    public function setValue(?float $value): void
    {
        $this->changeText(text: $value === null ? '' : $this->toText(value: $value));
    }

    /**
     * The shortest text that parses back to the same value, without exponent (the parser does not accept it).
     */
    private function toText(float $value): string
    {
        if (!is_finite(num: $value)) {
            throw new InvalidArgumentException(
                message: 'The value of field ' . $this->name . ' must be a finite number.',
            );
        }
        $text = json_encode(value: $value, flags: JSON_THROW_ON_ERROR);
        if (str_contains(haystack: $text, needle: 'e')) {
            $text = number_format(num: $value, decimals: 20, thousands_separator: '');
        }

        if (!str_contains(haystack: $text, needle: '.')) {
            return $text;
        }

        return rtrim(string: rtrim(string: $text, characters: '0'), characters: '.');
    }
}
