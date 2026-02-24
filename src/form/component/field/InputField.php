<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\InputFieldRenderer;
use actra\yuf\form\settings\AutoCompleteValue;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;

abstract class InputField extends FormField
{
    public function __construct(
        public readonly InputTypeValue $inputType,
        string $name,
        HtmlText $label,
        int|float|string|bool|null $value,
        public readonly ?string $placeholder,
        public readonly ?AutoCompleteValue $autoComplete,
        public readonly ?int $maxLength = null
    ) {
        parent::__construct(
            name: $name,
            label: $label,
            value: $value
        );
    }

    public function getDefaultRenderer(): FormRenderer
    {
        return new InputFieldRenderer(formField: $this);
    }
}