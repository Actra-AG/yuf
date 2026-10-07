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
use actra\yuf\form\rule\StringRule;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * An options field with one selected key. The value is the key, `''` means none.
 */
abstract class SingleOptionsField extends OptionsField
{
    private string $value = '';
    private string $initialValue = '';
    /** @var list<StringRule> */
    private array $rules = [];

    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        ?string $initialValue,
        ?AutoCompleteEnum $autoComplete,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            autoComplete: $autoComplete,
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
     */
    public function setValue(?string $value): void
    {
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

    /**
     * Adds a rule for the selected key. Rules run for a selected option only.
     */
    public function addRule(StringRule $formRule): void
    {
        $this->rules[] = $formRule;
    }

    protected function checkRules(): void
    {
        if ($this->isValueEmpty()) {
            return;
        }
        foreach ($this->rules as $rule) {
            if (!$rule->validate(value: $this->value)) {
                $this->addError(errorMessage: $rule->getErrorMessage());
            }
        }
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
}
