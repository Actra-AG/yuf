<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormOptions;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

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
        public readonly ?AutoCompleteEnum $autoComplete,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
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
            errorMessage: str_replace(search: '[field]', replace: $this->name, subject: $this->messages->invalidOption),
        );
    }

    /**
     * The key as integer: strictly integer-formatted only (same rules as `DbRow::getInt()`), for the options that
     * were added with `FormOptions::addIntItem()`.
     *
     * @throws UnexpectedValueException If the key is not an integer (the options are not integer ids).
     */
    final protected function keyToInt(string $key): int
    {
        return FormOptions::toIntKey(key: $key) ?? throw new UnexpectedValueException(
            message: 'The key "' . (
                strlen(string: $key) > 40 ? substr(string: $key, offset: 0, length: 40) . '...' : $key
            )
            . '" of field ' . $this->name . ' is not an integer or out of the integer range. Use getValueAsString() '
            . 'or getValues() for options with text keys.',
        );
    }
}
