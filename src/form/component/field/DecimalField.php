<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\AmountParser;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;
use InvalidArgumentException;
use TypeError;
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
        ?AutoCompleteValue $autoComplete = null,
        ?int $maxLength = null
    ) {
        if ($scale < 0) {
            throw new InvalidArgumentException(
                message: 'The scale of field ' . $name . ' must not be negative, ' . $scale . ' given.'
            );
        }
        parent::__construct(
            inputType: InputTypeValue::TEXT,
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
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     *
     * The parameter is declared `mixed` only while the legacy `FormField::setValue(mixed)` bridge exists; it becomes
     * `?string` with the removal of the bridge.
     *
     * @throws TypeError If the value is not a string or `null`.
     * @throws InvalidArgumentException If the string is not a decimal or has more decimals than the scale.
     */
    public function setValue(mixed $value): void
    {
        if ($value !== null && !is_string(value: $value)) {
            throw $this->createValueTypeError(expectedType: 'a decimal string or null', value: $value);
        }
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