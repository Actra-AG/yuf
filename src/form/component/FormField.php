<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\FormComponent;
use actra\yuf\form\FormInput;
use actra\yuf\form\FormMessages;
use actra\yuf\form\listener\FormFieldListener;
use actra\yuf\html\HtmlText;
use LogicException;

/**
 * A field of a form. It holds no value: the families of fields (text, options, boolean, files, ...) hold their value
 * with a precise type and have their typed getters, setters and rules. This base owns what does not depend on the
 * value: label, info, errors, listeners, the required check and the `validate()` template.
 */
abstract class FormField extends FormComponent
{
    public ?HtmlText $fieldInfo = null;
    public ?HtmlText $labelInfoText = null;
    public ?HtmlText $additionalColumnContent = null;
    /** The form of the field; set by `Form::addField()`, and by a toggle field for its children. */
    public Form $topFormComponent {
        get => $this->topForm ?? throw new LogicException(
            message: 'The field ' . $this->name . ' is not part of a form yet. Add it with Form::addField().',
        );
        set {
            $this->topForm = $value;
        }
    }
    public bool $renderRequiredAbbr = true;
    public string $id;
    public private(set) HtmlText $label;
    public private(set) bool $renderLabel = true;
    public bool $autoFocus = false;
    /** The texts of the form; set by `Form::addField()`, a field without a form uses the English defaults. */
    public FormMessages $messages;
    /** @var list<FormFieldListener> */
    protected array $listeners = [];

    private ?HtmlText $requiredErrorMessage = null;
    private ?Form $topForm = null;
    private bool $inputReceived = false;
    private bool $inputRejected = false;
    private bool $validatesInput = false;

    /**
     * @param string $name The internal name for this formField which is also used by the renderer (name="")
     * @param HtmlText $label The field label to be used by the renderer
     * @param ?HtmlText $labelInfoText Additional text padded to the displayed label-name (see FileField max-Info, for
     *                                 example)
     */
    public function __construct(
        string    $name,
        HtmlText  $label,
        ?HtmlText $labelInfoText = null,
    ) {
        $this->id = $name;
        $this->label = $label;
        parent::__construct(name: $name);
        $this->messages = new FormMessages();
        $this->labelInfoText = $labelInfoText;
    }

    public function hasTopFormComponent(): bool
    {
        return $this->topForm !== null;
    }

    /**
     * The text of the current value for the renderer, HTML-encoded.
     */
    abstract public function renderValue(): string;

    /**
     * Whether the field has no value (the empty value of its type).
     */
    abstract public function isValueEmpty(): bool;

    /**
     * Returns whether the current value differs from the initial value (the constructor value).
     */
    abstract public function valueHasChanged(): bool;

    /**
     * Reads the value of this field from the request. Input of the wrong shape is rejected with `rejectInput()`.
     */
    abstract protected function readInput(FormInput $input): void;

    /**
     * Hook for request input besides the field's own value (e.g. the country code of a phone number or the pointer
     * of uploaded files). It runs before the value is read, so the value can depend on it.
     */
    protected function readAdditionalInput(FormInput $input): void {}

    /**
     * Hook: adds one error per failed rule. Called for input that was not rejected; the families check their typed
     * rules here (never for an empty value).
     */
    protected function checkRules(): void {}

    /**
     * Hook: validates the fields that depend on this one (the children of a toggle field), after this field is valid.
     */
    protected function validateChildFields(FormInput $input): void {}

    /**
     * Hook: validates the dependent fields with their current values, when `validateCurrentValue()` is called directly
     * (not as part of `validate()`) and this field is valid.
     */
    protected function validateChildFieldsWithCurrentValues(): void {}

    /**
     * Adds the check that the field is not empty. Replaces an earlier required message.
     */
    public function addRequiredRule(HtmlText $errorMessage): void
    {
        $this->requiredErrorMessage = $errorMessage;
    }

    public function isRequired(): bool
    {
        return $this->requiredErrorMessage !== null;
    }

    public function addListener(FormFieldListener $formFieldListener): void
    {
        $this->listeners[] = $formFieldListener;
    }

    /**
     * Reads the input of the field from the request and validates it: runs the listeners, the required check and the
     * rules of the field, then validates the dependent fields (see `validateChildFields()`) if it is valid.
     *
     * @return bool : Validation result (false on error)
     */
    final public function validate(FormInput $input): bool
    {
        $this->startReadingInput();
        $this->readAdditionalInput(input: $input);
        $this->readInput(input: $input);
        // The children are validated with the input below, not with their current values
        $this->validatesInput = true;
        try {
            $isValid = $this->validateCurrentValue();
        } finally {
            $this->validatesInput = false;
        }
        if ($isValid) {
            $this->validateChildFields(input: $input);
        }

        return !$this->hasErrors(withChildElements: true);
    }

    /**
     * Runs the listeners, the required check and the rules on the current value, without reading input. Rules do not
     * run for rejected input (the one error about the invalid input is enough).
     *
     * @return bool : Validation result (false on error)
     */
    public function validateCurrentValue(): bool
    {
        $this->inputReceived = true;

        foreach ($this->listeners as $formFieldListener) {
            if ($this->isValueEmpty()) {
                $formFieldListener->onEmptyValueBeforeValidation(
                    form: $this->topFormComponent,
                    formField: $this,
                );
            } else {
                $formFieldListener->onNotEmptyValueBeforeValidation(
                    form: $this->topFormComponent,
                    formField: $this,
                );
            }
        }

        if (!$this->inputRejected) {
            $this->checkRequired();
            $this->checkRules();
        }

        $hasErrors = $this->hasErrors(withChildElements: false);
        foreach ($this->listeners as $formFieldListener) {
            if ($this->isValueEmpty()) {
                $formFieldListener->onEmptyValueAfterValidation(
                    form: $this->topFormComponent,
                    formField: $this,
                );
            } else {
                $formFieldListener->onNotEmptyValueAfterValidation(
                    form: $this->topFormComponent,
                    formField: $this,
                );
            }

            if ($hasErrors) {
                $formFieldListener->onValidationError(
                    form: $this->topFormComponent,
                    formField: $this,
                );
            } else {
                $formFieldListener->onValidationSuccess(
                    form: $this->topFormComponent,
                    formField: $this,
                );
            }
        }
        if (!$hasErrors && !$this->validatesInput) {
            $this->validateChildFieldsWithCurrentValues();
        }

        return !$this->hasErrors(withChildElements: true);
    }

    private function checkRequired(): void
    {
        if ($this->requiredErrorMessage !== null && $this->isValueEmpty()) {
            $this->addError(errorMessage: $this->requiredErrorMessage);
        }
    }

    /**
     * Marks the field as having received input and forgets that the previous input was rejected.
     */
    final protected function startReadingInput(): void
    {
        $this->inputReceived = true;
        $this->inputRejected = false;
    }

    /**
     * The input of the field is invalid (wrong shape, manipulated): one error, the typed rules do not run.
     */
    final protected function rejectInput(string $errorMessage): void
    {
        $this->inputRejected = true;
        $this->addError(errorMessage: HtmlText::unencoded(textContent: $errorMessage));
    }

    /**
     * Guard of the protected `setInitialValue()` methods: the initial value can only be set while the field has not
     * received input.
     *
     * @throws LogicException If `validate()` or `validateCurrentValue()` has already run on this field.
     */
    final protected function assertInitialValueCanBeSet(): void
    {
        if ($this->inputReceived) {
            throw new LogicException(
                message: 'The initial value of field ' . $this->name . ' cannot be set after the field has been '
                . 'validated. Use the public setter to change the current value.',
            );
        }
    }

    /**
     * Suppresses the VISIBLE label rendering of the associated input field
     * (It will be still readable by screen readers)
     */
    public function setRenderLabelFalse(): void
    {
        $this->renderLabel = false;
    }
}
