<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormComponent;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormOptions;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\ToggleFieldRenderer;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\html\HtmlText;
use Closure;

/**
 * Radio options that show child components under the selected option. See `MultiToggleField` for checkboxes.
 */
class ToggleField extends SingleOptionsField
{
    private readonly ToggleChildren $toggleChildren;
    /** @var array<int|string, array<int|string, FormComponent>> */
    public array $childrenByMainOption {
        get => $this->toggleChildren->getAll();
    }

    public function __construct(
        string $name,
        HtmlText $label,
        FormOptions $formOptions,
        ?string $initialValue,
        ?HtmlText $requiredError = null,
        private readonly bool $displayLegend = true,
        ?AutoCompleteEnum $autoComplete = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            formOptions: $formOptions,
            initialValue: $initialValue,
            autoComplete: $autoComplete,
        );
        $this->toggleChildren = new ToggleChildren(toggleField: $this);
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
        // The toggle markup has always been fixed (the renderer the form would set is not used)
        $this->setRenderer(renderer: $this->getDefaultRenderer());
    }

    public function addChildField(string $mainOption, FormField $childField): void
    {
        $this->addChildComponent(mainOption: $mainOption, childComponent: $childField);
    }

    public function addChildComponent(string $mainOption, FormComponent $childComponent): void
    {
        $this->toggleChildren->add(mainOption: $mainOption, childComponent: $childComponent);
        $childComponent->setParentFormComponent(parentFormComponent: $this);
    }

    public function getChildField(string $mainOption, string $fieldName): FormField
    {
        return $this->toggleChildren->getField(mainOption: $mainOption, fieldName: $fieldName);
    }

    public function getChildComponent(string $mainOption, string $componentName): FormComponent
    {
        return $this->toggleChildren->get(mainOption: $mainOption, componentName: $componentName);
    }

    /**
     * @param Closure(FormField): FormRenderer $rendererFactory Creates the renderer for a child field without one
     */
    public function setDefaultChildFieldRenderer(Closure $rendererFactory): void
    {
        $this->toggleChildren->setDefaultChildFieldRenderer(rendererFactory: $rendererFactory);
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new ToggleFieldRenderer(
            toggleField: $this,
            toggleChildren: $this->toggleChildren,
            displayLegend: $this->displayLegend,
        );
    }

    /**
     * Validates the child fields of the selected options with the same input, after this field is valid.
     */
    protected function validateChildFields(FormInput $input): void
    {
        $this->toggleChildren->validateSelected(input: $input);
    }

    /**
     * Validates the child fields of the selected options with their current values, after this field is valid.
     */
    protected function validateChildFieldsWithCurrentValues(): void
    {
        $this->toggleChildren->validateSelectedCurrentValues();
    }
}
