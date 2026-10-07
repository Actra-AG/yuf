<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\form\renderer\DefaultCollectionRenderer;
use LogicException;
use Override;

abstract class FormCollection extends FormComponent
{
    /**
     * @var array<int|string, FormComponent> The child components by name (numeric names become int keys), can also be
     *     collections
     */
    public private(set) array $childComponents = [];

    final public function addChildComponent(FormComponent $formComponent): void
    {
        $childComponentName = $formComponent->name;
        if (array_key_exists(key: $childComponentName, array: $this->childComponents)) {
            throw new LogicException(
                'There is already an existing child component with the same name: ' . $childComponentName,
            );
        }
        $formComponent->setParentFormComponent($this);

        $this->childComponents[$childComponentName] = $formComponent;
    }

    public function getChildComponent(string $childComponentName): FormComponent
    {
        if (!array_key_exists(key: $childComponentName, array: $this->childComponents)) {
            throw new LogicException(
                'FormCollection ' . $this->name . ' does not contain requested ChildComponent ' . $childComponentName,
            );
        }

        return $this->childComponents[$childComponentName];
    }

    public function hasChildComponent(string $childComponentName): bool
    {
        return array_key_exists($childComponentName, $this->childComponents);
    }

    public function removeChildComponent(string $childComponentName): void
    {
        if (!$this->hasChildComponent($childComponentName)) {
            throw new LogicException(
                'FormCollection ' . $this->name . ' does not contain requested ChildComponent ' . $childComponentName,
            );
        }
        unset($this->childComponents[$childComponentName]);
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new DefaultCollectionRenderer($this);
    }
}
