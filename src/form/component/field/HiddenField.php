<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\component\field;

use actra\yuf\form\renderer\HiddenFieldRenderer;
use actra\yuf\form\settings\InputTypeValue;
use actra\yuf\html\HtmlText;

final class HiddenField extends SettableStringInputField
{
    public function __construct(string $name, ?string $value = null)
    {
        parent::__construct(
            inputType: InputTypeValue::HIDDEN,
            name: $name,
            label: HtmlText::encoded(textContent: ''),
            value: $value,
            placeholder: null,
            autoComplete: null
        );
        $this->setRenderer(renderer: new HiddenFieldRenderer(hiddenField: $this));
    }

    /**
     * A hidden value is sent back exactly as rendered, so it is not trimmed.
     */
    protected function normalize(string $input): string
    {
        return $this->removeZeroWidthSpaces(input: $input);
    }
}