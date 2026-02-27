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
use actra\yuf\form\FormRule;
use actra\yuf\form\listener\FormFieldListener;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
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
    /** @var FormFieldListener[] */
    protected array $listeners = [];

    // Renderer options:
    private mixed $value;
    private mixed $originalValue = null;
    /** @var FormRule[] */
    private array $rules = [];
    private bool $acceptArrayAsValue = false;

    /**
     * @param string $name The internal name for this formField which is also used by the renderer (name="")
     * @param HtmlText $label The field label to be used by the renderer
     * @param mixed $value The original value for this formField. Depending on the specific field, it can be a string, float, integer, and even an array. By default, it is null.
     * @param ?HtmlText $labelInfoText Additional text padded to the displayed label-name (see FileField max-Info, for example)
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

        if (is_array(value: $value)) {
            // If the value to be pre-filled is already an array, we can also accept an array as user input
            $this->acceptArrayAsValue();
        }

        $this->setValue(value: $value);
        $this->setOriginalValue(value: $value);
        $this->labelInfoText = $labelInfoText;
    }

    protected function acceptArrayAsValue(): void
    {
        $this->acceptArrayAsValue = true;
    }

    public function setValue($value): void
    {
        if (
            is_array(value: $value)
            && !$this->isArrayAsValueAllowed()
        ) {
            $this->addError(
                errorMessage: 'Die ungültige Eingabe wurde ignoriert.',
                isEncodedForRendering: true
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
        return HtmlEncoder::encode(value: $this->getRawValue());
    }

    public function getRawValue(bool $returnNullIfEmpty = false)
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

    public function getOriginalValue()
    {
        return $this->originalValue;
    }

    public function setOriginalValue($value): void
    {
        $this->originalValue = $value;
    }

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
     * @param array $inputData : All input data
     * @param bool $overwriteValue : Overwrite current value by value from inputData (true by default)
     *
     * @return bool : Validation result (false on error)
     */
    public function validate(array $inputData, bool $overwriteValue = true): bool
    {
        if ($overwriteValue) {
            $defaultValue = $this->isArrayAsValueAllowed() ? [] : null;
            $this->setValue(
                value: array_key_exists(
                    key: $this->name,
                    array: $inputData
                ) ? $inputData[$this->name] : $defaultValue
            );
        }

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

        foreach ($this->rules as $formRule) {
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