<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\rule\ValidAmountRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class AmountField extends TextField
{
    public function __construct(
        string $name,
        HtmlText $label,
        bool $valueIsFloat,
        null|int|float $initialValue = null,
        ?HtmlText $individualInvalidError = null,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null,
        ?int $maxLength = null
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            value: (string)$initialValue,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength
        );
        $this->addRule(
            formRule: new ValidAmountRule(
                valueIsFloat: $valueIsFloat,
                errorMessage: is_null(value: $individualInvalidError) ? HtmlText::encoded(
                    textContent: 'Der angegebene Wert ist ungültig.'
                ) : $individualInvalidError
            )
        );
    }

    /**
     * Returns the value as `int`, or `null` if the field is empty (`null`, `''`, whitespace only).
     *
     * Accepts exactly what ValidAmountRule accepts for integers: optional sign and digits, surrounding whitespace
     * (`'+5'`, `'007'`, `' 12 '`), no decimals or exponent notation. Call it after a successful validation.
     *
     * @throws UnexpectedValueException If the value is not an integer (e.g. `'1.5'`, `'abc'`, before validation or
     *         after a failed validation) or does not fit into an `int` (outside PHP_INT_MIN..PHP_INT_MAX).
     */
    public function getValueAsInt(): ?int
    {
        return $this->getValueAsIntOrFail();
    }

    /**
     * Returns the value as `float`, or `null` if the field is empty (`null`, `''`, whitespace only).
     *
     * Accepts exactly what ValidAmountRule accepts for decimals: optional sign, digits with an optional decimal point
     * (`'5'`, `'1.5'`, `'1.'`, `'.5'`), surrounding whitespace, no exponent notation. Call it after a successful
     * validation. Integer fields work too (`'5'` gives `5.0`).
     *
     * @throws UnexpectedValueException If the value is not a decimal number (e.g. `'abc'`, `'1e3'`, before validation
     *         or after a failed validation).
     */
    public function getValueAsFloat(): ?float
    {
        return $this->getValueAsFloatOrFail();
    }
}