<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * A field whose value is one or several keys of its `FormOptions`: `SingleOptionsField` (one key) or
 * `MultiOptionsField` (a list of keys). The keys are checked against the options when the input is read; an unknown
 * key is an "invalid option" error with the empty value.
 */
abstract class OptionsField extends FormField
{
    public ?HtmlText $listDescription = null;
    /** @var list<string> */
    private array $listTagClasses = [];

    public function __construct(
        string $name,
        HtmlText $label,
        public FormOptions $formOptions,
        public readonly ?AutoCompleteValue $autoComplete
    ) {
        parent::__construct(
            name: $name,
            label: $label
        );
    }

    /**
     * Whether the option with this key is part of the current value (compared exactly, as strings).
     */
    abstract public function isSelected(string $optionKey): bool;

    /**
     * Whether the field holds a list of keys (a fixed property of the class: `name[]` is posted).
     */
    abstract public function isMultiple(): bool;

    abstract protected function readInput(FormInput $input): void;

    public function addListTagClass(string $className): void
    {
        $this->listTagClasses[] = $className;
    }

    /**
     * @return list<string>
     */
    public function getListTagClasses(): array
    {
        return array_values(array: array_unique(array: $this->listTagClasses));
    }

    /**
     * The posted key is not one of the options (manipulated input): one error, the rules do not run. The text can
     * not happen in normal circumstances, so there is no individual message per field.
     */
    final protected function rejectInvalidOption(): void
    {
        $this->rejectInput(
            errorMessage: str_replace(search: '[field]', replace: $this->name, subject: $this->messages->invalidOption)
        );
    }

    /**
     * @param array<array-key, mixed> $inputData
     * @internal Bridge until `validate(FormInput)` replaces `validate(array)`.
     */
    protected function readInputData(array $inputData): void
    {
        $this->readInput(input: FormInput::fromArray(data: $inputData));
    }

    /**
     * @internal Bridge until all fields have typed values.
     */
    protected function initializeLegacyValue(mixed $value): void
    {
    }

    /**
     * @internal Bridge until all fields have typed values.
     * @throws LogicException Always: removed, pass the value to the constructor or use `setInitialValue()`.
     */
    public function setOriginalValue(mixed $value): void
    {
        throw new LogicException(
            message: 'setOriginalValue() was removed. Pass the value to the constructor of field ' . $this->name
            . ' or call setInitialValue() or setInitialValues() in a subclass.'
        );
    }
}