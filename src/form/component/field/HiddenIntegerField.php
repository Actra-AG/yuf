<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\AmountParser;
use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;
use TypeError;
use UnexpectedValueException;

/**
 * A hidden whole number (e.g. an ID, `?int`, `null` if empty). Manipulated input (text, decimals, a number outside of
 * the `int` range) is a validation error, so `getValueAsInt()` never fails after a successful validation.
 */
final class HiddenIntegerField extends ParsedInputField
{
    private ?int $value = null;

    public function __construct(string $name, ?int $value = null)
    {
        parent::__construct(
            inputType: InputTypeValue::HIDDEN,
            name: $name,
            label: HtmlText::encoded(textContent: ''),
            invalidError: null,
            requiredError: null,
            placeholder: null,
            autoComplete: null
        );
        $this->setRenderer(renderer: new HiddenFieldRenderer(hiddenField: $this));
        if ($value !== null) {
            $this->changeInitialText(text: (string)$value);
        }
    }

    /**
     * A hidden value is sent back as rendered, so only zero-width spaces are removed (the parser ignores whitespace).
     */
    protected function normalize(string $input): string
    {
        return $this->removeZeroWidthSpaces(input: $input);
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
     * Changes the current value only, the initial value stays.
     *
     * The parameter is declared `mixed` only while the legacy `FormField::setValue(mixed)` bridge exists; it becomes
     * `?int` with the removal of the bridge.
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
}