<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\FormRenderer;
use actra\yuf\form\renderer\InputFieldRenderer;
use actra\yuf\form\settings\AutoCompleteEnum;
use actra\yuf\form\settings\InputTypeEnum;
use actra\yuf\html\HtmlText;
use Override;

abstract class InputField extends TextualField
{
    public function __construct(
        public readonly InputTypeEnum $inputType,
        string $name,
        HtmlText $label,
        public readonly ?string $placeholder,
        public readonly ?AutoCompleteEnum $autoComplete,
        public readonly ?int $maxLength = null,
    ) {
        parent::__construct(
            name: $name,
            label: $label,
        );
    }

    #[Override]
    public function getDefaultRenderer(): FormRenderer
    {
        return new InputFieldRenderer(formField: $this);
    }
}
