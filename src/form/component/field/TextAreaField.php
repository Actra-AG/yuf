<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\TextAreaRenderer;
use actra\yuf\form\rule\StringRule;
use actra\yuf\html\HtmlText;
use LogicException;
use Override;
use UnexpectedValueException;

class TextAreaField extends TextualField
{
    public private(set) int $rows;
    public private(set) int $cols;
    /** @var list<string> */
    public private(set) array $cssClassesForRenderer = [];
    private ?string $placeholder = null;
    /** @var list<StringRule> */
    private array $lineRules = [];

    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value = null,
        ?HtmlText $requiredError = null,
        int $rows = 4,
        int $cols = 50,
    ) {
        $this->rows = $rows;
        $this->cols = $cols;

        parent::__construct(name: $name, label: $label);
        if ($value !== null) {
            $this->changeInitialText(text: $value);
        }

        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
    }

    /**
     * Leading spaces, indentation and line breaks are part of the text, so it is not trimmed.
     */
    #[Override]
    protected function normalize(string $input): string
    {
        return $this->removeZeroWidthSpaces(input: $input);
    }

    public function addCssClassForRenderer(string $className): void
    {
        $this->cssClassesForRenderer[] = $className;
    }

    public function getPlaceholder(): ?string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(string $placeholder): void
    {
        $this->placeholder = $placeholder;
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new TextAreaRenderer($this);
    }

    /**
     * Returns the text as posted (not trimmed, no HTML encoding). `''` if the field is empty.
     */
    public function getValueAsString(): string
    {
        return $this->getText();
    }

    /**
     * Changes the current value only, the initial value stays.
     */
    public function setValue(string $value): void
    {
        $this->changeText(text: $value);
    }

    /**
     * Sets the current and the initial value. For subclasses that fill the field after `parent::__construct()`.
     *
     * @throws LogicException If the field has already been validated.
     */
    protected function setInitialValue(string $value): void
    {
        $this->changeInitialText(text: $value);
    }

    /**
     * Adds a rule that is applied to every line of `getValues()` (trimmed, no empty lines); a rule that fails for
     * one or more lines adds its error message once.
     */
    public function addEachRule(StringRule $formRule): void
    {
        $this->lineRules[] = $formRule;
    }

    #[Override]
    protected function checkRules(): void
    {
        parent::checkRules();
        if ($this->lineRules === []) {
            return;
        }
        $lines = $this->getValues();
        foreach ($this->lineRules as $rule) {
            if (!array_all(array: $lines, callback: static fn(string $line): bool => $rule->validate(value: $line))) {
                $this->addError(errorMessage: $rule->getErrorMessage());
            }
        }
    }

    /**
     * Returns the text as list of lines: split at line breaks (CRLF, LF or CR), every line trimmed, empty lines
     * removed. A blank text gives `[]`.
     *
     * @return list<string>
     */
    public function getValues(): array
    {
        // Explicit line breaks and no /u: never splits a byte inside a multibyte character, works with invalid UTF-8.
        $lines = preg_split(pattern: '/\r\n|\n|\r/', subject: $this->getValueAsString());
        if ($lines === false) {
            throw new UnexpectedValueException(
                message: 'The value of field ' . $this->name . ' cannot be split into lines.',
            );
        }

        $values = [];
        foreach ($lines as $line) {
            $line = trim(string: $line);
            if ($line !== '') {
                $values[] = $line;
            }
        }

        return $values;
    }
}
