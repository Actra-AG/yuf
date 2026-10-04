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
use LogicException;
use TypeError;
use UnexpectedValueException;

/**
 * A whole number (`?int`, `null` if empty): optional sign and digits, surrounding whitespace is ignored, no decimals,
 * no exponent, no value outside of the `int` range. Input that is not an integer is kept for re-rendering and gives
 * the field's error.
 *
 * It renders the canonical text of the value (`'+007'` becomes `7`), so leading zeros are not kept: use a
 * `TextField` with a `RegexRule` for codes with leading zeros. For prices use `DecimalField`, for measurements
 * `FloatField`.
 */
class IntegerField extends ParsedInputField
{
    private ?int $value = null;

    public function __construct(
        string $name,
        HtmlText $label,
        ?int $initialValue = null,
        ?HtmlText $individualInvalidError = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null,
        ?int $maxLength = null
    ) {
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
            $this->changeInitialText(text: (string)$initialValue);
        }
    }

    protected function accept(string $text): void
    {
        $this->value = AmountParser::toInt(value: $text);
        parent::accept(text: $this->value === null ? $text : (string)$this->value);
    }

    protected function hasParsedValue(): bool
    {
        return $this->value !== null;
    }

    /**
     * Returns the value, or `null` if the field is empty.
     *
     * @throws UnexpectedValueException If the field holds input that is not an integer (before validation or after a
     *         failed validation).
     */
    public function getValueAsInt(): ?int
    {
        $this->assertValueCanBeRead(type: 'integer');

        return $this->value;
    }

    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     *
     * The parameter is declared `mixed` only while the legacy `FormField::setValue(mixed)` bridge exists (PHP does
     * not allow narrowing it); it becomes `?int` with the removal of the bridge.
     *
     * @throws TypeError If the value is not an `int` or `null`.
     */
    public function setValue(mixed $value): void
    {
        if ($value !== null && !is_int(value: $value)) {
            throw $this->createValueTypeError(expectedType: 'an int or null', value: $value);
        }
        $this->changeText(text: $value === null ? '' : (string)$value);
    }

    /**
     * Sets the current and the initial value. For subclasses that fill the field after `parent::__construct()`.
     *
     * @throws LogicException If the field has already been validated.
     */
    protected function setInitialValue(?int $value): void
    {
        $this->changeInitialText(text: $value === null ? '' : (string)$value);
    }
}