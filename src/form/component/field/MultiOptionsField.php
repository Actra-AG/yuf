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
use actra\yuf\html\HtmlText;
use LogicException;
use TypeError;

/**
 * An options field with a list of selected keys (`name[]` is posted). The empty value is `[]`; empty keys (`''`) are
 * dropped everywhere (input, constructor, setters), so `getValues()` needs no filtering.
 */
abstract class MultiOptionsField extends OptionsField
{
    /** @var list<string> */
    private array $values = [];
    /** @var list<string> */
    private array $initialValues = [];

    /**
     * @param list<string> $initialValues
     */
    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        array $initialValues,
        ?AutoCompleteValue $autoComplete
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            autoComplete: $autoComplete
        );
        $this->setInitialValues(values: $initialValues);
    }

    /**
     * Returns the selected keys in their stored order. The keys are not checked against the options (the input is).
     *
     * @return list<string>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Changes the current values only, the initial values stay (so `valueHasChanged()` compares with them).
     *
     * @param list<string> $values
     * @throws TypeError If an entry is not a string.
     */
    public function setValues(array $values): void
    {
        $this->values = $this->toKeyList(values: $values);
    }

    /**
     * Sets the current and the initial values. For subclasses that fill the field after `parent::__construct()`.
     *
     * @param list<string> $values
     * @throws LogicException If the field has already been validated.
     * @throws TypeError If an entry is not a string.
     */
    protected function setInitialValues(array $values): void
    {
        $this->assertInitialValueCanBeSet();
        $this->values = $this->toKeyList(values: $values);
        $this->initialValues = $this->values;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return list<string>
     */
    private function toKeyList(array $values): array
    {
        $keys = [];
        foreach ($values as $value) {
            if (!is_string(value: $value)) {
                throw new TypeError(
                    message: 'The values of field ' . $this->name . ' must be strings, ' . get_debug_type(value: $value)
                    . ' given.'
                );
            }
            if ($value !== '') {
                $keys[] = $value;
            }
        }

        return $keys;
    }

    /**
     * The keys that are selected now but were not part of the initial values.
     *
     * @return list<string>
     */
    public function getAddedValues(): array
    {
        return array_values(
            array: array_filter(
                array: $this->values,
                callback: fn(string $key): bool => !in_array(needle: $key, haystack: $this->initialValues, strict: true)
            )
        );
    }

    /**
     * The keys of the initial values that are not selected any more.
     *
     * @return list<string>
     */
    public function getRemovedValues(): array
    {
        return array_values(
            array: array_filter(
                array: $this->initialValues,
                callback: fn(string $key): bool => !in_array(needle: $key, haystack: $this->values, strict: true)
            )
        );
    }

    public function isSelected(string $optionKey): bool
    {
        return in_array(needle: $optionKey, haystack: $this->values, strict: true);
    }

    final public function isMultiple(): bool
    {
        return true;
    }

    public function isValueEmpty(): bool
    {
        return $this->values === [];
    }

    /**
     * Compares the selection, the order of the keys does not matter.
     */
    public function valueHasChanged(): bool
    {
        return $this->getAddedValues() !== [] || $this->getRemovedValues() !== [];
    }

    /**
     * A list has no single text: the selected keys are rendered by the options.
     */
    public function renderValue(): string
    {
        return '';
    }

    /**
     * Reads the values from the request: every key of a list must be an option (empty keys are dropped), a missing
     * value is empty. A single text (`name=a` instead of `name[]=a`), a non-string entry and an unknown key are
     * rejected: the value is reset to `[]`, one error is added and the rules do not run.
     */
    final protected function readInput(FormInput $input): void
    {
        $list = $input->getList(name: $this->name);
        match ($input->getShape(name: $this->name)) {
            InputShapeEnum::LIST => $this->acceptKeys(keys: $list ?? []),
            InputShapeEnum::MISSING => $this->values = [],
            InputShapeEnum::TEXT, InputShapeEnum::INVALID => $this->rejectInputShape(),
        };
    }

    /**
     * @param list<string> $keys
     */
    private function acceptKeys(array $keys): void
    {
        $keys = $this->toKeyList(values: $keys);
        foreach ($keys as $key) {
            if (!$this->formOptions->exists(key: $key)) {
                $this->values = [];
                $this->rejectInvalidOption();

                return;
            }
        }
        $this->values = $keys;
    }

    private function rejectInputShape(): void
    {
        $this->values = [];
        $this->rejectInput(errorMessage: $this->messages->invalidInput);
    }

    /**
     * @return ($returnNullIfEmpty is true ? ?list<string> : list<string>)
     * @internal Bridge until all fields have typed values: use `getValues()`.
     */
    public function getRawValue(bool $returnNullIfEmpty = false): ?array
    {
        return $returnNullIfEmpty && $this->isValueEmpty() ? null : $this->values;
    }

    /**
     * @return list<string>
     * @internal Bridge until all fields have typed values: the original value is the initial value.
     */
    public function getOriginalValue(): array
    {
        return $this->initialValues;
    }

    /**
     * @internal Bridge until all fields have typed values.
     * @throws LogicException Always: a multi field has a list of values.
     */
    public function setValue(mixed $value): void
    {
        throw new LogicException(
            message: 'The field ' . $this->name . ' holds a list of values, use setValues() instead of setValue().'
        );
    }
}