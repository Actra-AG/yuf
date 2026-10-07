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
use actra\yuf\form\rule\StringRule;
use actra\yuf\html\HtmlEncoder;
use LogicException;

/**
 * A field whose request value is one text. It owns the pipeline of the input text: `normalize()`, then `accept()`.
 * It has no public value getter or setter, because its subclasses have different value types. Its rules check the
 * text (`addRule()`).
 */
abstract class TextualField extends FormField
{
    private string $text = '';
    private string $initialText = '';
    /** @var list<StringRule> */
    private array $rules = [];

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
        $text = $input->getText(name: $this->name);
        match ($input->getShape(name: $this->name)) {
            InputShapeEnum::TEXT => $this->accept(text: $this->normalize(input: $text ?? '')),
            InputShapeEnum::MISSING => $this->accept(text: ''),
            InputShapeEnum::LIST, InputShapeEnum::INVALID => $this->rejectTextInput(),
        };
    }

    private function rejectTextInput(): void
    {
        $this->accept(text: '');
        $this->rejectInput(errorMessage: $this->messages->invalidInput);
    }

    /**
     * Adds a rule for the text of the field (the normalized text, also of number and date fields). Rules run for a
     * non-empty text only.
     */
    public function addRule(StringRule $formRule): void
    {
        $this->rules[] = $formRule;
    }

    protected function checkRules(): void
    {
        if ($this->isValueEmpty()) {
            return;
        }
        foreach ($this->rules as $rule) {
            if (!$rule->validate(value: $this->text)) {
                $this->addError(errorMessage: $rule->getErrorMessage());
            }
        }
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
}
