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
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\html\HtmlText;
use Override;

/**
 * A select with one selected option. See `MultiSelectOptionsField` for a multiple selection.
 *
 * Extension point: a project can extend it to fill its options (a select of a database table, a search select).
 */
class SelectOptionsField extends SingleOptionsField
{
    use HasSelectOptionsPresentation;

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
        ?AutoCompleteEnum $autoComplete = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValue,
            autoComplete: $autoComplete,
        );
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        $this->initializeSelectOptionsPresentation(
            isRequired: $requiredError !== null,
            individualEmptyValueLabel: $individualEmptyValueLabel,
            cssClasses: $cssClasses,
            renderAsChosenEnhancedField: $renderAsChosenEnhancedField,
            renderEmptyValueOption: $renderEmptyValueOption,
            placeholder: $placeholder,
        );
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new SelectOptionsRenderer(selectOptionsField: $this);
    }
}
