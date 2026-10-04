<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\AmountParser;
use actra\yuf\form\FormFieldValueMissingException;
use actra\yuf\form\rule\DecimalRule;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * A decimal number as a string with a fixed number of decimals (`?string`, `null` if empty), for money. The value is
 * a canonical decimal string like `'12.50'` (scale 2), so no float rounding can occur; do the arithmetic with bcmath
 * (`bcadd()`, ...).
 *
 * Accepted: optional sign, digits with an optional dot (`12`, `12.5`, `.5`, `1.`), surrounding whitespace is ignored.
 * Not accepted: a comma, exponent notation, thousands separators and more decimals than `scale` (never rounded,
 * a trailing zero counts: `'1.500'` has three decimals). Input that is not valid is kept for re-rendering and gives
 * the field's error.
 */
final class DecimalField extends ParsedInputField
{
    private ?string $value = null;
    /** @var list<DecimalRule> */
    private array $valueRules = [];

    /**
     * @param int $scale The number of decimals, 0 or more (`2` for CHF).
     * @param ?string $initialValue A decimal string with at most `$scale` decimals.
     * @throws InvalidArgumentException If the scale is negative or the initial value is not a valid decimal string.
     */
    public function __construct(
        string $name,
        HtmlText $label,
        public readonly int $scale,
        ?string $initialValue = null,
        ?HtmlText $individualInvalidError = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null,
        ?int $maxLength = null
    ) {
        if ($scale < 0) {
            throw new InvalidArgumentException(
                message: 'The scale of field ' . $name . ' must not be negative, ' . $scale . ' given.'
            );
        }
        parent::__construct(
            inputType: InputTypeEnum::TEXT,
            name: $name,
            label: $label,
            invalidError: $individualInvalidError,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength
        );
        if ($initialValue !== null) {
            $this->changeInitialText(text: $this->validDecimal(value: $initialValue));
        }
    }

    protected function accept(string $text): void
    {
        $this->value = AmountParser::toDecimal(value: $text, scale: $this->scale);
        parent::accept(text: $this->value ?? $text);
    }

    protected function hasParsedValue(): bool
    {
        return $this->value !== null;
    }

    /**
     * Adds a rule for the value (e.g. `DecimalMinRule`). Value rules run for a parsed, non-empty value only.
     */
    public function addValueRule(DecimalRule $formRule): void
    {
        $this->valueRules[] = $formRule;
    }

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
     * Returns the canonical decimal string (e.g. `'12.50'`), or `null` if the field is empty.
     *
     * @throws UnexpectedValueException If the field holds input that is not a valid decimal (before validation or
     *         after a failed validation).
     */
    public function getValueAsDecimal(): ?string
    {
        $this->assertValueCanBeRead(type: 'decimal');

        return $this->value;
    }

    /**
     * Returns the value of a required field. Use it after a successful `validate()`, the nullable getter for an
     * optional field.
     *
     * @throws FormFieldValueMissingException If the field is empty (not validated yet, or not required).
     * @throws UnexpectedValueException If the field holds input that is not valid.
     */
    public function getRequiredValueAsDecimal(): string
    {
        return $this->getValueAsDecimal() ?? throw $this->valueMissing(nullableGetter: 'getValueAsDecimal');
    }

    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     *
     * @throws InvalidArgumentException If the string is not a decimal or has more decimals than the scale.
     */
    public function setValue(?string $value): void
    {
        $this->changeText(text: $value === null ? '' : $this->validDecimal(value: $value));
    }

    private function validDecimal(string $value): string
    {
        $decimal = AmountParser::toDecimal(value: $this->normalize(input: $value), scale: $this->scale);
        if ($decimal === null) {
            throw new InvalidArgumentException(
                message: 'The value of field ' . $this->name . ' must be a decimal number with at most ' . $this->scale
                . ' decimals, "' . $value . '" given.'
            );
        }

        return $decimal;
    }
}