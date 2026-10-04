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
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\html\HtmlText;

/**
 * A select with one selected option. See `MultiSelectOptionsField` for a multiple selection.
 */
class SelectOptionsField extends SingleOptionsField
{
    use SelectOptionsSettings;

    /**
     * @param list<string> $cssClasses
     */
    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        ?string $initialValue,
        ?HtmlText $requiredError = null,
        ?HtmlText $individualEmptyValueLabel = null,
        array $cssClasses = [],
        bool $renderAsChosenEnhancedField = false,
        bool $renderEmptyValueOption = true,
        ?string $placeholder = null,
        ?AutoCompleteValue $autoComplete = null
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValue,
            autoComplete: $autoComplete
        );
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        $this->initializeSelectOptionsSettings(
            isRequired: $requiredError !== null,
            individualEmptyValueLabel: $individualEmptyValueLabel,
            cssClasses: $cssClasses,
            renderAsChosenEnhancedField: $renderAsChosenEnhancedField,
            renderEmptyValueOption: $renderEmptyValueOption,
            placeholder: $placeholder
        );
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new SelectOptionsRenderer(selectOptionsField: $this);
    }
}