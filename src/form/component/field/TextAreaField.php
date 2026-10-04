<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\TextAreaRenderer;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\html\HtmlText;
use LogicException;
use TypeError;
use UnexpectedValueException;

class TextAreaField extends TextualField
{
    private(set) int $rows;
    private(set) int $cols;
    /** @var list<string> */
    private(set) array $cssClassesForRenderer = [];
    private ?string $placeholder = null;

    public function __construct(
        string $name,
        HtmlText $label,
        ?string $value = null,
        ?HtmlText $requiredError = null,
        int $rows = 4,
        int $cols = 50
    ) {
        $this->rows = $rows;
        $this->cols = $cols;

        parent::__construct(name: $name, label: $label);
        if ($value !== null) {
            $this->changeInitialText(text: $value);
        }

        if (!is_null($requiredError)) {
            $this->addRule(new RequiredRule($requiredError));
        }
    }

    /**
     * Leading spaces, indentation and line breaks are part of the text, so it is not trimmed.
     */
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
     *
     * The parameter is declared `mixed` only while the legacy `FormField::setValue(mixed)` bridge exists (PHP does
     * not allow narrowing it); it becomes `string` with the removal of the bridge.
     *
     * @throws TypeError If the value is not a string.
     */
    public function setValue(mixed $value): void
    {
        if (!is_string(value: $value)) {
            throw new TypeError(
                message: 'The value of field ' . $this->name . ' must be a string, ' . get_debug_type(value: $value)
                . ' given.'
            );
        }
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
                message: 'The value of field ' . $this->name . ' cannot be split into lines.'
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