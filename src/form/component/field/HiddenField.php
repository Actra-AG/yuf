<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\rule\ValidAmountRule;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class HiddenField extends InputField
{
    /**
     * @param bool $valueIsInt Only accept integer values (e.g. IDs): manipulated input becomes a validation error, so
     *                         getValueAsInt() never fails after a successful validation.
     */
    public function __construct(
        string $name,
        int|float|string|bool|null $value = null,
        bool $valueIsInt = false
    ) {
        parent::__construct(
            inputType: InputTypeValue::HIDDEN,
            name: $name,
            label: HtmlText::encoded(textContent: ''),
            value: $value,
            placeholder: null,
            autoComplete: null
        );
        $this->setRenderer(renderer: new HiddenFieldRenderer(hiddenField: $this));
        if ($valueIsInt) {
            $this->addRule(
                formRule: new ValidAmountRule(
                    valueIsFloat: false,
                    errorMessage: HtmlText::encoded(textContent: 'Der angegebene Wert ist ungültig.')
                )
            );
        }
    }

    /**
     * Returns the value as `int`, or `null` if the field is empty (`null`, `''`, whitespace only).
     *
     * Works like AmountField::getValueAsInt(): a posted string may have a sign, leading zeros and surrounding
     * whitespace (`'+5'`, `'007'`, `' 12 '`); an `int` constructor value is returned as is. Construct the field with
     * `valueIsInt: true` to turn manipulated input into a validation error instead of an exception here.
     *
     * @throws UnexpectedValueException If the value is not an integer (a decimal or text string, a `float` or `bool`
     *         constructor value) or does not fit into an `int` (outside PHP_INT_MIN..PHP_INT_MAX).
     */
    public function getValueAsInt(): ?int
    {
        return $this->getValueAsIntOrFail();
    }
}