<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormInput;
use actra\yuf\form\InputShapeEnum;
use actra\yuf\html\HtmlEncoder;
use LogicException;

/**
 * A field whose request value is one text. It owns the pipeline of the input text: `normalize()`, then `accept()`.
 * It has no public value getter or setter, because its subclasses have different value types.
 */
abstract class TextualField extends FormField
{
    private string $text = '';
    private string $initialText = '';

    /**
     * Cleans the text before it is stored (posted input, setters and constructor values): removes zero-width spaces
     * and trims. Subclasses override it for other rules.
     */
    protected function normalize(string $input): string
    {
        return trim(string: $this->removeZeroWidthSpaces(input: $input));
    }

    final protected function removeZeroWidthSpaces(string $input): string
    {
        return str_replace(search: "\xE2\x80\x8B", replace: '', subject: $input);
    }

    /**
     * Stores the normalized text as the current value. Typed subclasses override it to parse the text, and call the
     * parent to keep the text.
     */
    protected function accept(string $text): void
    {
        $this->text = $text;
    }

    final protected function getText(): string
    {
        return $this->text;
    }

    /**
     * Changes the current text only (the initial text stays), without adding an error.
     */
    final protected function changeText(string $text): void
    {
        $this->accept(text: $this->normalize(input: $text));
    }

    /**
     * Sets the current and the initial text.
     *
     * @throws LogicException If the field has already been validated.
     */
    final protected function changeInitialText(string $text): void
    {
        $this->assertInitialValueCanBeSet();
        $normalized = $this->normalize(input: $text);
        $this->accept(text: $normalized);
        $this->initialText = $normalized;
    }

    /**
     * Reads the value of this field from the request: TEXT is normalized and accepted, MISSING gives the empty text,
     * a list or manipulated input is rejected (empty value, one error, no rules).
     */
    final protected function readInput(FormInput $input): void
    {
        $this->readAdditionalInput(input: $input);
        $text = $input->getText(name: $this->name);
        match ($input->getShape(name: $this->name)) {
            InputShapeEnum::TEXT => $this->accept(text: $this->normalize(input: $text ?? '')),
            InputShapeEnum::MISSING => $this->accept(text: ''),
            InputShapeEnum::LIST, InputShapeEnum::INVALID => $this->rejectTextInput(),
        };
    }

    /**
     * Hook for request input besides the field's own value (e.g. the country code of a phone number or a fallback
     * token from the query string). It runs before the value is read, so the value can depend on it.
     */
    protected function readAdditionalInput(FormInput $input): void
    {
    }

    private function rejectTextInput(): void
    {
        $this->accept(text: '');
        $this->rejectInput(errorMessage: $this->messages->invalidInput);
    }

    public function isValueEmpty(): bool
    {
        return trim(string: $this->text) === '';
    }

    public function valueHasChanged(): bool
    {
        return $this->text !== $this->initialText;
    }

    public function renderValue(): string
    {
        return HtmlEncoder::encode(value: $this->text);
    }

    /**
     * Validates the field with request data that carries the query part (the array based `validate()` has none).
     *
     * @internal Bridge until `validate(FormInput)` replaces `validate(array, bool)`.
     */
    final public function validateInput(FormInput $input): bool
    {
        $this->startReadingInput();
        $this->readInput(input: $input);

        return $this->validateCurrentValue();
    }

    /**
     * @param array<array-key, mixed> $inputData
     * @internal Bridge until `validate(FormInput)` replaces `validate(array)`.
     */
    protected function readInputData(array $inputData): void
    {
        $this->readInput(input: FormInput::fromArray(data: $inputData));
    }

    /**
     * @internal Bridge until all fields have typed values.
     */
    protected function initializeLegacyValue(mixed $value): void
    {
    }

    /**
     * @return ($returnNullIfEmpty is true ? ?string : string)
     * @internal Bridge until all fields have typed values: use `getValueAsString()` (or the typed getter).
     */
    public function getRawValue(bool $returnNullIfEmpty = false): ?string
    {
        return $returnNullIfEmpty && $this->isValueEmpty() ? null : $this->text;
    }

    /**
     * @internal Bridge until all fields have typed values: fields with a public setter override it (the typed setter
     *           `setValue(string)`), the others have none.
     * @throws LogicException
     */
    public function setValue(mixed $value): void
    {
        throw new LogicException(message: 'The field ' . $this->name . ' has no setter.');
    }

    /**
     * @internal Bridge until all fields have typed values: the initial value is the constructor value.
     */
    public function getOriginalValue(): string
    {
        return $this->initialText;
    }

    /**
     * @internal Bridge until all fields have typed values.
     * @throws LogicException Always: removed, pass the value to the constructor or use `setInitialValue()`.
     */
    public function setOriginalValue(mixed $value): void
    {
        throw new LogicException(
            message: 'setOriginalValue() was removed. Pass the value to the constructor of field ' . $this->name
            . ' or call setInitialValue() in a subclass.'
        );
    }
}