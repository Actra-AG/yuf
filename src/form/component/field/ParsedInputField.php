<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

/**
 * An input field whose text is parsed into a typed value (number, date, time) in `accept()`. Text that cannot be
 * parsed is kept for re-rendering, the typed value is `null` and validation adds the invalid-value error. This base
 * only owns that shared handling, each subclass owns its native value and its typed getter and setters.
 */
abstract class ParsedInputField extends InputField
{
    public function __construct(
        InputTypeEnum $inputType,
        string $name,
        HtmlText $label,
        private readonly ?HtmlText $invalidError,
        ?HtmlText $requiredError,
        ?string $placeholder,
        ?AutoCompleteEnum $autoComplete,
        ?int $maxLength = null
    ) {
        parent::__construct(
            inputType: $inputType,
            name: $name,
            label: $label,
            placeholder: $placeholder,
            autoComplete: $autoComplete,
            maxLength: $maxLength
        );
        if ($requiredError !== null) {
            $this->addRequiredRule(errorMessage: $requiredError);
        }
    }

    /**
     * Whether the current text was parsed into the typed value.
     */
    abstract protected function hasParsedValue(): bool;

    /**
     * The field holds text that is not empty but could not be parsed (the invalid input is kept for re-rendering).
     */
    final protected function holdsUnparsableText(): bool
    {
        return !$this->isValueEmpty() && !$this->hasParsedValue();
    }

    /**
     * @param string $type The type named in the message, e.g. `integer`.
     * @throws UnexpectedValueException If the field holds text that could not be parsed.
     */
    final protected function assertValueCanBeRead(string $type): void
    {
        if (!$this->holdsUnparsableText()) {
            return;
        }
        $text = $this->getText();
        throw new UnexpectedValueException(
            message: 'The value of field ' . $this->name . ' cannot be read as ' . $type . ', it is not valid: "'
            . (strlen(string: $text) > 40 ? substr(string: $text, offset: 0, length: 40) . '...' : $text) . '"'
        );
    }

    public function validateCurrentValue(): bool
    {
        if ($this->holdsUnparsableText()) {
            if ($this->invalidError === null) {
                $this->addError(errorMessage: HtmlText::unencoded(textContent: $this->messages->invalidValue));
            } else {
                $this->addError(errorMessage: $this->invalidError);
            }
        }

        return parent::validateCurrentValue();
    }
}