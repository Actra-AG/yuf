<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\collection;

use actra\yuf\form\component\field\CsrfTokenField;
use actra\yuf\form\component\field\MultiToggleField;
use actra\yuf\form\component\field\ToggleField;
use actra\yuf\form\component\FormControl;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormCollection;
use actra\yuf\form\FormComponent;
use actra\yuf\form\FormContext;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\CompactFieldRenderer;
use actra\yuf\form\renderer\DefaultFormRenderer;
use actra\yuf\form\renderer\DefinitionListRenderer;
use actra\yuf\html\HtmlText;
use actra\yuf\security\CsrfTokenSource;
use LogicException;
use Override;

/**
 * The form names must be unique per page: the name is the sent indicator and the prefix of the field names.
 * A form without CSRF token source in its `FormContext` (no session) has no CSRF field and checks no token.
 *
 * Extension point: a project form extends `Form`, adds its fields in the constructor and offers methods for the
 * validated values.
 */
class Form extends FormCollection
{
    public readonly string $sentIndicator;
    /** @var list<string> */
    public private(set) array $cssClasses = [];
    private bool $renderRequiredAbbr = true;
    private bool $compactFields = false;

    public function __construct(
        public readonly FormContext $context,
        string $name,
        public readonly bool $acceptUpload = false,
        public readonly ?HtmlText $globalErrorMessage = null,
        public readonly bool $methodPost = true,
        ?string $individualSentIndicator = null,
        public readonly bool $disableClientValidation = false,
        public readonly FormMessages $messages = new FormMessages(),
    ) {
        $this->sentIndicator = $individualSentIndicator === null ? $name : $individualSentIndicator;
        parent::__construct(name: $name);

        $csrfTokenSource = $context->csrfTokenSource;
        // A GET form must not change state and would put the token into the URL, so it has no CSRF token
        if ($methodPost && $csrfTokenSource !== null) {
            $this->addField(formField: new CsrfTokenField(tokenSource: $csrfTokenSource));
        }
    }

    public function addField(FormField $formField): void
    {
        if (!$this->renderRequiredAbbr) {
            $formField->renderRequiredAbbr = false;
        }
        $formField->messages = $this->messages;
        $formField->topFormComponent = $this;
        $this->addChildComponent(formComponent: $formField);
    }

    public function removeCsrfProtection(): void
    {
        if ($this->hasChildComponent(childComponentName: CsrfTokenSource::FIELD_NAME)) {
            $this->removeChildComponent(childComponentName: CsrfTokenSource::FIELD_NAME);
        }
    }

    public function getDefaultFormFieldRenderer(FormField $formField): FormRenderer
    {
        if ($this->compactFields) {
            return new CompactFieldRenderer(formField: $formField);
        }

        return new DefinitionListRenderer(formField: $formField);
    }

    /**
     * Renders the fields that have no renderer of their own as label and control (`CompactFieldRenderer`) instead of
     * a definition list, e.g. for a search form. Call it before the form is rendered.
     */
    public function useCompactFieldRenderer(): void
    {
        $this->compactFields = true;
    }

    public function addCssClass(string $className): void
    {
        $this->cssClasses[] = $className;
    }

    public function addComponent(FormComponent $formComponent): void
    {
        if ($formComponent instanceof FormControl) {
            $formComponent->messages = $this->messages;
        }
        $this->addChildComponent(formComponent: $formComponent);
    }

    public function removeField(string $name): void
    {
        if (!$this->hasField(name: $name)) {
            throw new LogicException(
                message: 'Form ' . $this->name . ' has no field ' . $name
                    . ' (it does not exist or is not a FormField).',
            );
        }
        $this->removeChildComponent(childComponentName: $name);
    }

    public function hasField(string $name): bool
    {
        if (!$this->hasChildComponent(childComponentName: $name)) {
            return false;
        }

        $component = $this->getChildComponent(childComponentName: $name);

        return ($component instanceof FormField);
    }

    /**
     * Validates all fields with the request data if the form was sent.
     *
     * @param ?FormInput $input The request data, by default the input of the request of the `FormContext` (the post
     *                          data or, for a GET form, the query string)
     */
    public function validate(?FormInput $input = null): bool
    {
        $input ??= $this->createInput();
        if (!$this->isSent(input: $input)) {
            return false;
        }

        foreach ($this->childComponents as $formComponent) {
            // The CSRF token is only checked if the other fields are valid (validateCsrf())
            if (!$formComponent instanceof FormField || $formComponent instanceof CsrfTokenField) {
                continue;
            }

            $formComponent->validate(input: $input);
        }

        if (!$this->hasErrors(withChildElements: true)) {
            $this->validateCsrf(input: $input);
        }

        if (
            $this->hasErrors(withChildElements: true)
            && !$this->hasErrors(withChildElements: false)
            && $this->globalErrorMessage !== null
        ) {
            $this->addError(errorMessage: $this->globalErrorMessage);
        }

        return !$this->hasErrors(withChildElements: true);
    }

    /**
     * Whether the sent indicator is in the query string of the request.
     *
     * @param ?FormInput $input The request data, by default the input of the request of the `FormContext`
     */
    public function isSent(?FormInput $input = null): bool
    {
        $input ??= $this->createInput();
        return $input->hasQueryKey(key: $this->sentIndicator);
    }

    private function createInput(): FormInput
    {
        return FormInput::fromHttpRequest(httpRequest: $this->context->httpRequest, methodPost: $this->methodPost);
    }

    private function validateCsrf(FormInput $input): void
    {
        if (!$this->hasChildComponent(childComponentName: CsrfTokenSource::FIELD_NAME)) {
            // The Csrf protection has been disabled
            return;
        }
        $csrfTokenField = $this->getField(name: CsrfTokenSource::FIELD_NAME);
        if (!$csrfTokenField instanceof CsrfTokenField) {
            return;
        }
        if (!$csrfTokenField->validate(input: $input)) {
            $this->addError(errorMessage: HtmlText::fromText(text: $this->messages->invalidCsrfToken));
        }
    }

    public function getField(string $name): FormField
    {
        $childComponent = $this->getChildComponent(childComponentName: $name);
        if (!($childComponent instanceof FormField)) {
            throw new LogicException(
                message: 'The component ' . $name . ' of form ' . $this->name . ' is not a FormField.',
            );
        }

        return $childComponent;
    }

    #[Override]
    public function render(): string
    {
        if (
            $this->hasErrors(withChildElements: true)
            && !$this->hasErrors(withChildElements: false)
            && $this->globalErrorMessage !== null
        ) {
            $this->addError(errorMessage: $this->globalErrorMessage);
        }

        return parent::render();
    }

    /**
     * @return list<FormField>
     */
    public function getAllFields(): array
    {
        $allFields = [];
        foreach ($this->childComponents as $formComponent) {
            if (!$formComponent instanceof FormField) {
                continue;
            }
            $allFields[] = $formComponent;
        }

        return $allFields;
    }

    /**
     * Whether the current value of any field differs from its initial value (`FormField::valueHasChanged()`), also
     * of the child fields of toggle fields. The CSRF field never counts; a typed password and an uploaded file do.
     * Meaningful after `validate()` (the posted values) or after the setters were called.
     */
    public function hasChanges(): bool
    {
        return array_any(
            array: $this->getAllFields(),
            callback: static fn(FormField $formField): bool => $formField->valueHasChanged()
                || Form::hasChangedChildField(formField: $formField),
        );
    }

    private static function hasChangedChildField(FormField $formField): bool
    {
        if (!$formField instanceof ToggleField && !$formField instanceof MultiToggleField) {
            return false;
        }
        foreach ($formField->childrenByMainOption as $children) {
            foreach ($children as $child) {
                if (
                    $child instanceof FormField
                    && ($child->valueHasChanged() || Form::hasChangedChildField(formField: $child))
                ) {
                    return true;
                }
            }
        }

        return false;
    }

    public function dontRenderRequiredAbbr(): void
    {
        $this->renderRequiredAbbr = false;
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new DefaultFormRenderer(form: $this);
    }
}
