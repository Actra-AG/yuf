<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\common\TimeOfDay;
use actra\yuf\form\FormFieldValueMissingException;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

/**
 * A time of the day (`?TimeOfDay`, `null` if empty). Input: `H:i` or `H:i:s` (`08:05`, `08:05:07`, 00:00 to 23:59:59).
 * Input that cannot be parsed is kept for re-rendering and gives `invalidError`. The field renders `H:i` (the seconds
 * of the value are not shown).
 */
final class TimeField extends ParsedInputField
{
    private ?TimeOfDay $value = null;

    public function __construct(
        string $name,
        HtmlText $label,
        ?TimeOfDay $value,
        HtmlText $invalidError,
        ?HtmlText $requiredError = null,
        ?string $placeholder = null,
        ?AutoCompleteEnum $autoComplete = null,
    ) {
        parent::__construct(
            inputType: InputTypeEnum::TIME,
            name: $name,
            label: $label,
            invalidError: $invalidError,
            requiredError: $requiredError,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
        );
        if ($value !== null) {
            $this->changeInitialText(text: $value->toString());
        }
    }

    protected function accept(string $text): void
    {
        $this->value = TimeOfDay::fromString(time: $text);
        parent::accept(text: $this->value?->toString() ?? $text);
    }

    protected function hasParsedValue(): bool
    {
        return $this->value !== null;
    }

    public function renderValue(): string
    {
        return $this->value === null ? parent::renderValue() : $this->value->toShortString();
    }

    /**
     * Returns the time, or `null` if the field is empty.
     *
     * @throws UnexpectedValueException If the field holds input that is not a time (before validation or after a
     *         failed validation).
     */
    public function getValueAsTimeOfDay(): ?TimeOfDay
    {
        $this->assertValueCanBeRead(type: 'time');

        return $this->value;
    }

    /**
     * Returns the value of a required field. Use it after a successful `validate()`, the nullable getter for an
     * optional field.
     *
     * @throws FormFieldValueMissingException If the field is empty (not validated yet, or not required).
     * @throws UnexpectedValueException If the field holds input that is not valid.
     */
    public function getRequiredValueAsTimeOfDay(): TimeOfDay
    {
        return $this->getValueAsTimeOfDay() ?? throw $this->valueMissing(nullableGetter: 'getValueAsTimeOfDay');
    }

    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it).
     */
    public function setValue(?TimeOfDay $value): void
    {
        $this->changeText(text: $value?->toString() ?? '');
    }
}
