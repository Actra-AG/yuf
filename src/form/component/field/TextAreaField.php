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