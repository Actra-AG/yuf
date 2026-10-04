<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component;

use ArrayObject;
use DateTime;
use actra\yuf\form\component\collection\Form;
use actra\yuf\form\FormComponent;
use actra\yuf\form\FormMessages;
use actra\yuf\form\FormRule;
use actra\yuf\form\listener\FormFieldListener;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use LogicException;
use UnexpectedValueException;

abstract class FormField extends FormComponent
{
    public ?HtmlText $fieldInfo = null;
    public ?HtmlText $labelInfoText = null;
    public ?HtmlText $additionalColumnContent = null;
    public Form $topFormComponent;
    public bool $renderRequiredAbbr = true;
    public string $id;
    private(set) HtmlText $label;
    private(set) bool $renderLabel = true;
    public bool $autoFocus = false;
    /** The texts of the form; set by `Form::addField()`, a field without a form uses the English defaults. */
    public FormMessages $messages;
    /** @var FormFieldListener[] */
    protected array $listeners = [];

    // Renderer options:
    private mixed $value;
    private mixed $originalValue = null;
    /** @var FormRule[] */
    private array $rules = [];
    private bool $acceptArrayAsValue = false;
    private bool $inputReceived = false;
    private bool $inputRejected = false;

    /**
     * @param string $name The internal name for this formField which is also used by the renderer (name="")
     * @param HtmlText $label The field label to be used by the renderer
     * @param mixed $value The original value for this formField. Depending on the specific field, it can be a string,
     *                     float, integer, and even an array. By default, it is null.
     * @param ?HtmlText $labelInfoText Additional text padded to the displayed label-name (see FileField max-Info, for
     *                                 example)
     */
    public function __construct(
        string    $name,
        HtmlText  $label,
        mixed     $value = null,
        ?HtmlText $labelInfoText = null
    )
    {
        $this->id = $name;
        $this->label = $label;
        parent::__construct(name: $name);
        $this->messages = new FormMessages();
        $this->initializeLegacyValue(value: $value);
        $this->labelInfoText = $labelInfoText;
    }

    /**
     * @internal Bridge until all fields have typed values (removed with `getRawValue()`): fields with a typed value
     *           override this and keep their value themselves.
     */
    protected function initializeLegacyValue(mixed $value): void
    {
        if (is_array(value: $value)) {
            // If the value to be pre-filled is already an array, we can also accept an array as user input
            $this->acceptArrayAsValue();
        }

        $this->setValue(value: $value);
        $this->setOriginalValue(value: $value);
    }

    protected function acceptArrayAsValue(): void
    {
        $this->acceptArrayAsValue = true;
    }

    /**
     * @internal Bridge until all fields have typed values: fields with a typed value have a typed setter instead.
     */
    public function setValue(mixed $value): void
    {
        if (
            is_array(value: $value)
            && !$this->isArrayAsValueAllowed()
        ) {
            $this->addError(
                errorMessage: $this->messages->invalidInput,
                isEncodedForRendering: false
            );

            return;
        }
        if (is_string(value: $value)) {
            $value = str_replace(
                search: "\xE2\x80\x8B",
                replace: '',
                subject: $value
            );
        }

        $this->value = $value;
    }

    protected function isArrayAsValueAllowed(): bool
    {
        return $this->acceptArrayAsValue;
    }

    public function renderValue(): string
    {
        $value = $this->getRawValue();
        if ($value !== null && !is_scalar(value: $value)) {
            throw new UnexpectedValueException(
                message: 'The value of field ' . $this->name . ' cannot be rendered, it is of type '
                . get_debug_type(value: $value) . '.'
            );
        }

        return HtmlEncoder::encode(value: $value);
    }

    /**
     * @internal Bridge until all fields have typed values: use the typed getter of the field.
     */
    public function getRawValue(bool $returnNullIfEmpty = false): mixed
    {
        if ($this->isValueEmpty() && $returnNullIfEmpty) {
            return null;
        }

        return $this->value;
    }

    public function isValueEmpty(): bool
    {
        if ($this->value === null) {
            return true;
        }

        if (is_scalar(value: $this->value)) {
            return (strlen(string: trim(string: (string)$this->value)) <= 0);
        } elseif (is_array(value: $this->value)) {
            return (count(value: array_filter(array: $this->value)) <= 0);
        } elseif ($this->value instanceof ArrayObject) {
            return (count(value: array_filter(array: (array)$this->value)) <= 0);
        } elseif ($this->value instanceof DateTime) {
            return false;
        } else {
            throw new UnexpectedValueException(message: 'Could not check value against emptiness');
        }
    }

    /**
     * @internal Bridge until all fields have typed values: the original value is the initial value of the field.
     */
    public function getOriginalValue(): mixed
    {
        return $this->originalValue;
    }

    /**
     * @internal Bridge until all fields have typed values: set the initial value in the constructor.
     */
    public function setOriginalValue(mixed $value): void
    {
        $this->originalValue = $value;
    }

    /**
     * @return list<mixed>
     */
    public function getAddedValues(): array
    {
        if (!is_array(value: $this->value) || !is_array(value: $this->originalValue)) {
            return [];
        }

        $addedValues = [];

        foreach ($this->value as $selectedValue) {
            if (!in_array(
                needle: $selectedValue,
                haystack: $this->originalValue
            )) {
                $addedValues[] = $selectedValue;
            }
        }

        return $addedValues;
    }

    /**
     * @return list<mixed>
     */
    public function getRemovedValues(): array
    {
        if (
            !is_array(value: $this->value)
            || !is_array(value: $this->originalValue)
        ) {
            return [];
        }

        $removedValues = [];

        foreach ($this->originalValue as $originalValue) {
            if (!in_array(
                needle: $originalValue,
                haystack: $this->value
            )) {
                $removedValues[] = $originalValue;
            }
        }

        return $removedValues;
    }

    public function addRequiredRule(HtmlText $errorMessage): void
    {
        $this->addRule(new RequiredRule($errorMessage));
    }

    public function addRule(FormRule $formRule): void
    {
        $this->rules[] = $formRule;
    }

    public function isRequired(): bool
    {
        return $this->hasRule(ruleClassName: RequiredRule::class);
    }

    protected function hasRule(string $ruleClassName): bool
    {
        return array_any($this->rules, fn($rule) => get_class(object: $rule) === $ruleClassName);
    }

    public function addListener(FormFieldListener $formFieldListener): void
    {
        $this->listeners[] = $formFieldListener;
    }

    /**
     * Use the rules to validate the input data.
     *
     * @param array<array-key, mixed> $inputData : All input data
     * @param bool $overwriteValue : Overwrite current value by value from inputData (true by default)
     *
     * @return bool : Validation result (false on error)
     * @internal The signature is a bridge until `validate(FormInput)` replaces it.
     */
    public function validate(array $inputData, bool $overwriteValue = true): bool
    {
        if ($overwriteValue) {
            $this->startReadingInput();
            $this->readInputData(inputData: $inputData);
        } else {
            $this->inputReceived = true;
        }

        return $this->validateCurrentValue();
    }

    /**
     * @param array<array-key, mixed> $inputData
     * @internal Bridge until all fields have typed values: fields with a typed value read a `FormInput` instead.
     */
    protected function readInputData(array $inputData): void
    {
        $defaultValue = $this->isArrayAsValueAllowed() ? [] : null;
        $this->setValue(
            value: array_key_exists(
                key: $this->name,
                array: $inputData
            ) ? $inputData[$this->name] : $defaultValue
        );
    }

    /**
     * Runs the listeners and rules on the current value, without reading input. Rules do not run for rejected input
     * (the one error about the invalid input is enough).
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
                    formField: $this
                );
            } else {
                $formFieldListener->onNotEmptyValueBeforeValidation(
                    form: $this->topFormComponent,
                    formField: $this
                );
            }
        }

        foreach ($this->inputRejected ? [] : $this->rules as $formRule) {
            if (!$formRule->validate(formField: $this)) {
                $this->addErrorAsHtmlTextObject(errorMessageObject: $formRule->getErrorMessage());
            }
        }

        $hasErrors = $this->hasErrors(withChildElements: false);
        foreach ($this->listeners as $formFieldListener) {
            if ($this->isValueEmpty()) {
                $formFieldListener->onEmptyValueAfterValidation(
                    form: $this->topFormComponent,
                    formField: $this
                );
            } else {
                $formFieldListener->onNotEmptyValueAfterValidation(
                    form: $this->topFormComponent,
                    formField: $this
                );
            }

            if ($hasErrors) {
                $formFieldListener->onValidationError(
                    form: $this->topFormComponent,
                    formField: $this
                );
            } else {
                $formFieldListener->onValidationSuccess(
                    form: $this->topFormComponent,
                    formField: $this
                );
            }
        }

        return !$this->hasErrors(withChildElements: true);
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
        $this->addError(errorMessage: $errorMessage, isEncodedForRendering: false);
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
                . 'validated. Use the public setter to change the current value.'
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

    /**
     * Returns whether the original value has changed (true) or not (false)
     *
     * @return bool
     */
    public function valueHasChanged(): bool
    {
        return ($this->value !== $this->originalValue);
    }
}