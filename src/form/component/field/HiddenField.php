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
use UnexpectedValueException;

class HiddenField extends SettableStringInputField
{
    /**
     * @param bool $valueIsInt Only accept integer values (e.g. IDs): manipulated input becomes a validation error, so
     *                         getValueAsInt() never fails after a successful validation. Temporary flag, replaced by
     *                         HiddenIntegerField.
     */
    public function __construct(
        string $name,
        ?string $value = null,
        private readonly bool $valueIsInt = false
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
    }

    /**
     * A hidden value is sent back exactly as rendered, so it is not trimmed.
     */
    protected function normalize(string $input): string
    {
        return $this->removeZeroWidthSpaces(input: $input);
    }

    public function validateCurrentValue(): bool
    {
        if ($this->valueIsInt && !$this->isValueEmpty() && AmountParser::toInt(value: $this->getText()) === null) {
            $this->addError(errorMessage: $this->messages->invalidValue, isEncodedForRendering: false);
        }

        return parent::validateCurrentValue();
    }

    /**
     * Returns the value as `int`, or `null` if the field is empty (`''`, whitespace only).
     *
     * A value may have a sign, leading zeros and surrounding whitespace (`'+5'`, `'007'`, `' 12 '`). Construct the
     * field with `valueIsInt: true` to turn manipulated input into a validation error instead of an exception here.
     *
     * @throws UnexpectedValueException If the value is not an integer or does not fit into an `int`.
     */
    public function getValueAsInt(): ?int
    {
        return $this->getValueAsIntOrFail();
    }
}