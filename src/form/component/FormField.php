<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component;

use ArrayObject;
use DateTime;
use actra\yuf\form\AmountParser;
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

    /**
     * Shared implementation of the `getValueAsString()` getters of single-value fields.
     *
     * - `null` becomes `''`, a string is returned unchanged (no trimming, no encoding).
     * - `int`, `float` and `bool` are converted like `renderValue()` does before HTML encoding (`true` is `'1'`,
     *   `false` is `''`, `1.0` is `'1'`).
     *
     * @throws UnexpectedValueException If the value is an array or any other type, which is a programming error
     *         (e.g. a multiple field or a field constructed with an array used as single-value field).
     */
    protected function getValueAsStringOrFail(): string
    {
        $value = $this->getRawValue();
        if ($value === null) {
            return '';
        }
        if (is_string(value: $value)) {
            return $value;
        }
        if (is_scalar(value: $value)) {
            return (string)$value;
        }

        throw new UnexpectedValueException(
            message: 'The value of field ' . $this->name . ' cannot be read as string, it is of type '
            . get_debug_type(value: $value) . '.'
        );
    }

    /**
     * Shared implementation of the `getValueAsInt()` getters of single-value fields.
     *
     * - `null`, an empty string and a whitespace-only string give `null`.
     * - A string is parsed with AmountParser: optional sign and digits, surrounding whitespace allowed.
     * - An `int` is returned unchanged.
     *
     * @throws UnexpectedValueException If the value is not an integer: a decimal or exponent string, text, a `float`,
     *         a `bool`, an array, any other type, or an integer string outside of PHP_INT_MIN..PHP_INT_MAX.
     */
    protected function getValueAsIntOrFail(): ?int
    {
        $value = $this->getRawValue();
        if ($value === null || (is_string(value: $value) && $this->isValueEmpty())) {
            return null;
        }
        if (is_int(value: $value)) {
            return $value;
        }
        if (!is_string(value: $value)) {
            throw $this->createNumericTypeException(target: 'integer', value: $value);
        }

        $result = AmountParser::toInt(value: $value);
        if ($result !== null) {
            return $result;
        }

        throw new UnexpectedValueException(
            message: 'The value of field ' . $this->name . ' cannot be read as integer, '
            . (AmountParser::isInteger(value: $value) ? 'it is out of the integer range' : 'it is not an integer')
            . ': ' . $this->describeValueForException(value: $value)
        );
    }

    /**
     * Shared implementation of the `getValueAsFloat()` getters of single-value fields.
     *
     * - `null`, an empty string and a whitespace-only string give `null`.
     * - A string is parsed with AmountParser: integer or decimal (`1.5`, `1.`, `.5`), no exponent notation,
     *   surrounding whitespace allowed.
     * - An `int` is converted, a finite `float` is returned unchanged.
     *
     * @throws UnexpectedValueException If the value is not a decimal number: text, exponent notation, a number too
     *         large for a `float`, a non-finite `float`, a `bool`, an array or any other type.
     */
    protected function getValueAsFloatOrFail(): ?float
    {
        $value = $this->getRawValue();
        if ($value === null || (is_string(value: $value) && $this->isValueEmpty())) {
            return null;
        }
        if (is_int(value: $value)) {
            return (float)$value;
        }
        if (is_float(value: $value) && is_finite(num: $value)) {
            return $value;
        }
        if (!is_string(value: $value)) {
            throw $this->createNumericTypeException(target: 'float', value: $value);
        }

        $result = AmountParser::toFloat(value: $value);
        if ($result !== null) {
            return $result;
        }

        throw new UnexpectedValueException(
            message: 'The value of field ' . $this->name . ' cannot be read as float, it is not a decimal number: '
            . $this->describeValueForException(value: $value)
        );
    }

    /**
     * Shared implementation of the `getValues()` getters of option fields (checkbox, select, toggle).
     *
     * Reports what is stored, it does not check against the options (validation does that).
     *
     * - `null` and `''` give `[]` ("nothing selected", like the empty option in a select). Any other string gives a
     *   list with this string, also for multiple fields that hold a single string.
     * - An array gives its entries in the stored order, re-indexed. Entries `''`, `null`, `false`, `0.0` and `[]` are
     *   dropped (nothing selected), an `int` is converted to string (renderers compare option keys loosely, so `1`
     *   and `'1'` are the same selection). `'0'` is kept: it is a valid option key.
     *
     * @return list<string>
     * @throws UnexpectedValueException If the value or one of its entries is of another type (e.g. a nested array
     *         from manipulated input). This never happens after a successful validation: ValidateAgainstOptions
     *         accepts only scalar entries, or an array that consists only of the dropped entries.
     */
    protected function getValuesAsStringListOrFail(): array
    {
        $value = $this->getRawValue();
        if ($value === null) {
            return [];
        }
        if (is_string(value: $value)) {
            return $value === '' ? [] : [$value];
        }
        if (!is_array(value: $value)) {
            throw new UnexpectedValueException(
                message: 'The value of field ' . $this->name . ' cannot be read as list of strings, it is of type '
                . get_debug_type(value: $value) . '.'
            );
        }

        $values = [];
        foreach ($value as $entry) {
            if (is_int(value: $entry)) {
                $values[] = (string)$entry;
                continue;
            }
            if (is_string(value: $entry)) {
                if ($entry !== '') {
                    $values[] = $entry;
                }
                continue;
            }
            if ($entry === null || $entry === false || $entry === 0.0 || $entry === []) {
                continue;
            }

            throw new UnexpectedValueException(
                message: 'The value of field ' . $this->name
                . ' cannot be read as list of strings, it contains an entry of type ' . get_debug_type(value: $entry)
                . '.'
            );
        }

        return $values;
    }

    private function createNumericTypeException(string $target, mixed $value): UnexpectedValueException
    {
        return new UnexpectedValueException(
            message: 'The value of field ' . $this->name . ' cannot be read as ' . $target . ', it is of type '
            . get_debug_type(value: $value) . '.'
        );
    }

    private function describeValueForException(string $value): string
    {
        $shortened = strlen(string: $value) > 40 ? substr(string: $value, offset: 0, length: 40) . '...' : $value;

        return '"' . $shortened . '"';
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
        $this->inputReceived = true;
        if ($overwriteValue) {
            $this->inputRejected = false;
            $this->readInputData(inputData: $inputData);
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