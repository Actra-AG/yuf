<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\TextAreaRenderer;
use actra\yuf\form\rule\RequiredRule;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;
use UnexpectedValueException;

class TextAreaField extends FormField
{
    private(set) int $rows;
    private(set) int $cols;
    private(set) array $cssClassesForRenderer = [];
    private ?string $placeholder = null;

    public function __construct(
        string $name,
        HtmlText $label,
        null|string|array $value = null,
        ?HtmlText $requiredError = null,
        int $rows = 4,
        int $cols = 50
    ) {
        $this->rows = $rows;
        $this->cols = $cols;

        parent::__construct($name, $label, $value);

        if (!is_null($requiredError)) {
            $this->addRule(new RequiredRule($requiredError));
        }
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
     * Returns the stored value as string, without trimming or HTML encoding: `null` is `''`, a string is returned
     * as is and an array value (list of lines) is joined with PHP_EOL, like renderValue() does.
     *
     * @throws UnexpectedValueException If an array entry is not a string or the value is of any other type.
     */
    public function getValueAsString(): string
    {
        $value = $this->getRawValue();
        if (!is_array(value: $value)) {
            return $this->getValueAsStringOrFail();
        }
        foreach ($value as $entry) {
            if (!is_string(value: $entry)) {
                throw new UnexpectedValueException(
                    message: 'The value of field ' . $this->name
                    . ' cannot be read as string, it contains an entry of type ' . get_debug_type(value: $entry) . '.'
                );
            }
        }

        return implode(separator: PHP_EOL, array: $value);
    }

    public function renderValue(): string
    {
        $currentValue = $this->getRawValue();
        if (is_null($currentValue)) {
            return '';
        }
        if (is_array($currentValue)) {
            $htmlArray = [];
            foreach ($currentValue as $row) {
                $htmlArray[] = HtmlEncoder::encode(value: $row);
            }

            return implode(separator: PHP_EOL, array: $htmlArray);
        }

        return HtmlEncoder::encode(value: $currentValue);
    }
}