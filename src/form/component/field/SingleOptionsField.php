<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\form\InputShapeEnum;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use LogicException;
use TypeError;

/**
 * An options field with one selected key. The value is the key, `''` means none.
 */
abstract class SingleOptionsField extends OptionsField
{
    private string $value = '';
    private string $initialValue = '';

    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        ?string $initialValue,
        ?AutoCompleteValue $autoComplete
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            autoComplete: $autoComplete
        );
        $this->setInitialValue(value: $initialValue);
    }

    /**
     * Returns the selected key, without HTML encoding. `''` if nothing is selected.
     */
    public function getValueAsString(): string
    {
        return $this->value;
    }

    /**
     * Changes the current value only, the initial value stays (so `valueHasChanged()` compares with it). `null`
     * selects nothing. The key is not checked against the options (the input is).
     *
     * The parameter is declared `mixed` only while the legacy `FormField::setValue(mixed)` bridge exists (PHP does
     * not allow narrowing it); it becomes `?string` with the removal of the bridge.
     *
     * @throws TypeError If the value is neither a string nor `null`.
     */
    public function setValue(mixed $value): void
    {
        if ($value !== null && !is_string(value: $value)) {
            throw new TypeError(
                message: 'The value of field ' . $this->name . ' must be a string or null, '
                . get_debug_type(value: $value) . ' given.'
            );
        }
        $this->value = $value ?? '';
    }

    /**
     * Sets the current and the initial value. For subclasses that fill the field after `parent::__construct()`.
     *
     * @throws LogicException If the field has already been validated.
     */
    protected function setInitialValue(?string $value): void
    {
        $this->assertInitialValueCanBeSet();
        $this->value = $value ?? '';
        $this->initialValue = $this->value;
    }

    public function isSelected(string $optionKey): bool
    {
        return $this->value === $optionKey;
    }

    final public function isMultiple(): bool
    {
        return false;
    }

    public function isValueEmpty(): bool
    {
        return $this->value === '';
    }

    public function valueHasChanged(): bool
    {
        return $this->value !== $this->initialValue;
    }

    public function renderValue(): string
    {
        return HtmlEncoder::encode(value: $this->value);
    }

    /**
     * Reads the value from the request: a text must be an option key (or empty), a missing value is empty, a list
     * or manipulated input is rejected. Rejected input resets the value, adds one error and skips the rules.
     */
    final protected function readInput(FormInput $input): void
    {
        $text = $input->getText(name: $this->name);
        match ($input->getShape(name: $this->name)) {
            InputShapeEnum::TEXT => $this->acceptKey(key: $text ?? ''),
            InputShapeEnum::MISSING => $this->value = '',
            InputShapeEnum::LIST, InputShapeEnum::INVALID => $this->rejectInputShape(),
        };
    }

    private function acceptKey(string $key): void
    {
        if ($key !== '' && !$this->formOptions->exists(key: $key)) {
            $this->value = '';
            $this->rejectInvalidOption();

            return;
        }
        $this->value = $key;
    }

    private function rejectInputShape(): void
    {
        $this->value = '';
        $this->rejectInput(errorMessage: $this->messages->invalidInput);
    }

    /**
     * @return ($returnNullIfEmpty is true ? ?string : string)
     * @internal Bridge until all fields have typed values: use `getValueAsString()`.
     */
    public function getRawValue(bool $returnNullIfEmpty = false): ?string
    {
        return $returnNullIfEmpty && $this->isValueEmpty() ? null : $this->value;
    }

    /**
     * @internal Bridge until all fields have typed values: the original value is the initial value.
     */
    public function getOriginalValue(): string
    {
        return $this->initialValue;
    }
}