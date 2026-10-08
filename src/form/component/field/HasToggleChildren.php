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
use actra\yuf\form\FormRenderer;
use Closure;
use Override;

/**
 * The child components of a toggle field per main option: adding, reading, the renderer of the child fields and their
 * validation. Shared by `ToggleField` and `MultiToggleField`, which have different parents (single and multiple
 * options).
 *
 * @phpstan-require-extends OptionsField
 */
trait HasToggleChildren
{
    private ?ToggleChildren $toggleChildren = null;
    /** @var array<int|string, array<int|string, FormComponent>> */
    public array $childrenByMainOption {
        get => $this->getToggleChildren()->getAll();
    }

    public function addChildField(string $mainOption, FormField $childField): void
    {
        $this->addChildComponent(mainOption: $mainOption, childComponent: $childField);
    }

    public function addChildComponent(string $mainOption, FormComponent $childComponent): void
    {
        $this->getToggleChildren()->add(mainOption: $mainOption, childComponent: $childComponent);
        $childComponent->setParentFormComponent(parentFormComponent: $this);
    }

    public function getChildField(string $mainOption, string $fieldName): FormField
    {
        return $this->getToggleChildren()->getField(mainOption: $mainOption, fieldName: $fieldName);
    }

    public function getChildComponent(string $mainOption, string $componentName): FormComponent
    {
        return $this->getToggleChildren()->get(mainOption: $mainOption, componentName: $componentName);
    }

    /**
     * @param Closure(FormField): FormRenderer $rendererFactory Creates the renderer for a child field without one
     */
    public function setDefaultChildFieldRenderer(Closure $rendererFactory): void
    {
        $this->getToggleChildren()->setDefaultChildFieldRenderer(rendererFactory: $rendererFactory);
    }

    private function getToggleChildren(): ToggleChildren
    {
        return $this->toggleChildren ??= new ToggleChildren(toggleField: $this);
    }

    /**
     * Validates the child fields of the selected options with the same input, after this field is valid.
     */
    #[Override]
    protected function validateChildFields(FormInput $input): void
    {
        $this->getToggleChildren()->validateSelected(input: $input);
    }

    /**
     * Validates the child fields of the selected options with their current values, after this field is valid.
     */
    #[Override]
    protected function validateChildFieldsWithCurrentValues(): void
    {
        $this->getToggleChildren()->validateSelectedCurrentValues();
    }
}
