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
use actra\yuf\form\renderer\DefinitionListRenderer;
use Closure;
use LogicException;

/**
 * The child components of a toggle field per main option (the option that shows them). Shared by `ToggleField` and
 * `MultiToggleField` by composition; `ToggleFieldRenderer` renders them.
 *
 * @internal
 */
final class ToggleChildren
{
    /** @var array<int|string, array<int|string, FormComponent>> */
    private array $childrenByMainOption = [];
    /** @var Closure(FormField): FormRenderer */
    private Closure $childRendererFactory;

    public function __construct(private readonly OptionsField $toggleField)
    {
        $this->childRendererFactory = static fn(FormField $childField): FormRenderer => new DefinitionListRenderer(
            formField: $childField,
        );
    }

    /**
     * @param Closure(FormField): FormRenderer $rendererFactory Creates the renderer for a child field without one
     */
    public function setDefaultChildFieldRenderer(Closure $rendererFactory): void
    {
        $this->childRendererFactory = $rendererFactory;
    }

    public function createDefaultChildRenderer(FormComponent $childComponent): FormRenderer
    {
        if ($childComponent instanceof FormField) {
            return ($this->childRendererFactory)($childComponent);
        }

        return $childComponent->getDefaultRenderer();
    }

    /**
     * @throws LogicException If the main option does not exist.
     */
    public function add(string $mainOption, FormComponent $childComponent): void
    {
        if (!$this->toggleField->formOptions->exists(key: $mainOption)) {
            throw new LogicException(message: 'The mainOption ' . $mainOption . ' does not exist!');
        }
        $this->childrenByMainOption[$mainOption][$childComponent->name] = $childComponent;
        $this->adoptForm(childComponent: $childComponent);
    }

    /**
     * @return array<int|string, array<int|string, FormComponent>>
     */
    public function getAll(): array
    {
        return $this->childrenByMainOption;
    }

    public function has(string $mainOption): bool
    {
        return array_key_exists(key: $mainOption, array: $this->childrenByMainOption);
    }

    /**
     * @return array<int|string, FormComponent>
     */
    public function getForMainOption(string $mainOption): array
    {
        return array_key_exists(key: $mainOption, array: $this->childrenByMainOption)
            ? $this->childrenByMainOption[$mainOption]
            : [];
    }

    /**
     * @throws LogicException If the main option has no such child.
     */
    public function get(string $mainOption, string $componentName): FormComponent
    {
        $children = $this->getForMainOption(mainOption: $mainOption);
        if (!array_key_exists(key: $componentName, array: $children)) {
            throw new LogicException(message: 'The mainOption ' . $mainOption . ' has no child ' . $componentName);
        }

        return $children[$componentName];
    }

    /**
     * @throws LogicException If the main option has no such child, or it is not a `FormField`.
     */
    public function getField(string $mainOption, string $fieldName): FormField
    {
        $childField = $this->get(mainOption: $mainOption, componentName: $fieldName);
        if (!$childField instanceof FormField) {
            throw new LogicException(
                message: 'The childField ' . $fieldName . ' of mainOption ' . $mainOption
                . ' is not an instance of FormField',
            );
        }

        return $childField;
    }

    /**
     * Validates the child fields of the selected main options with the same input.
     */
    public function validateSelected(FormInput $input): void
    {
        foreach ($this->listSelectedChildFields() as $childField) {
            $childField->validate(input: $input);
        }
    }

    /**
     * Validates the child fields of the selected main options with their current values (without input).
     */
    public function validateSelectedCurrentValues(): void
    {
        foreach ($this->listSelectedChildFields() as $childField) {
            $childField->validateCurrentValue();
        }
    }

    /**
     * @return list<FormField>
     */
    private function listSelectedChildFields(): array
    {
        $childFields = [];
        foreach ($this->childrenByMainOption as $mainOption => $children) {
            if (!$this->toggleField->isSelected(optionKey: (string) $mainOption)) {
                continue;
            }
            foreach ($children as $childComponent) {
                if (!$childComponent instanceof FormField) {
                    continue;
                }
                $this->adoptForm(childComponent: $childComponent);
                $childFields[] = $childComponent;
            }
        }

        return $childFields;
    }

    /**
     * A child gets the form and the messages of the toggle field, as soon as the toggle field is part of a form
     * (listeners of the child need the form). Done when a child is added and again before it is validated, because
     * the toggle field is usually added to the form after its children.
     */
    private function adoptForm(FormComponent $childComponent): void
    {
        if (!$childComponent instanceof FormField || !$this->toggleField->hasTopFormComponent()) {
            return;
        }
        $childComponent->topFormComponent = $this->toggleField->topFormComponent;
        $childComponent->messages = $this->toggleField->messages;
    }
}
