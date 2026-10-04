<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\SelectOptionsRenderer;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class SelectOptionsField extends OptionsField
{
    public readonly array $cssClasses;
    public readonly HtmlText $emptyValueLabel;
    private array $dataAttributesStorage = [];
    public array $dataAttributes {
        get => $this->dataAttributesStorage;
    }

    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        null|string|array $initialValue,
        ?HtmlText $requiredError = null,
        ?HtmlText $individualEmptyValueLabel = null,
        array $cssClasses = [],
        bool $renderAsChosenEnhancedField = false,
        public readonly bool $acceptMultipleSelections = false,
        public readonly bool $renderEmptyValueOption = true,
        public readonly ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null
    ) {
        $this->emptyValueLabel = $individualEmptyValueLabel ?? HtmlText::encoded(
            textContent: $requiredError === null ? '' : '-- Please select --'
        );
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValue,
            autoComplete: $autoComplete
        );
        if ($requiredError !== null) {
            $this->addRule(formRule: new RequiredRule(defaultErrorMessage: $requiredError));
        }
        if ($renderAsChosenEnhancedField) {
            $cssClasses[] = 'chosen';
        }
        $this->cssClasses = $cssClasses;
        if ($this->acceptMultipleSelections) {
            $this->acceptArrayAsValue();
        }
    }

    public function addDataAttribute(string $name, string $value): void
    {
        if (str_starts_with(
            haystack: $name,
            needle: 'data-'
        )) {
            $name = substr(string: $name, offset: 5);
        }
        $this->dataAttributesStorage[$name] = $value;
    }

    public function getDataAttributes(): array
    {
        return $this->dataAttributes;
    }

    /**
     * Returns the stored value as string, without trimming or HTML encoding: `null` is `''`, a string is returned
     * as is. A multiple selection field is not supported, see FormField::getValueAsStringOrFail() for other types.
     *
     * @throws UnexpectedValueException If the field accepts multiple selections, or if the stored value is an array or
     *         of any other unsupported type.
     */
    public function getValueAsString(): string
    {
        // A multiple selection field may also hold a single string, but its value is a list (rendered as such)
        if ($this->acceptMultipleSelections) {
            throw new UnexpectedValueException(
                message: 'The value of field ' . $this->name
                . ' cannot be read as string, it is a multiple selection field.'
            );
        }

        return $this->getValueAsStringOrFail();
    }

    /**
     * Returns the selected values as list, see FormField::getValuesAsStringListOrFail() for the exact rules. Also
     * works for a field that holds a single string (empty value gives `[]`, otherwise a list with this value).
     * The values are not checked against the options.
     *
     * @return list<string>
     * @throws UnexpectedValueException If the stored value contains an entry that is not a string (e.g. a nested
     *         array from manipulated input). Never thrown after a successful validation.
     */
    public function getValues(): array
    {
        return $this->getValuesAsStringListOrFail();
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new SelectOptionsRenderer(selectOptionsField: $this);
    }
}