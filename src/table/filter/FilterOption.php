<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\table\filter;

use actra\yuf\db\DbQueryData;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlText;

final readonly class FilterOption
{
    public HtmlText $label;

    /**
     * @param string|HtmlText $label A string is text and encoded, HTML of the application is `HtmlText::fromHtml()`
     */
    public function __construct(
        public string $identifier,
        string|HtmlText $label,
        public DbQueryData $whereCondition,
    ) {
        $this->label = is_string(value: $label) ? HtmlText::fromText(text: $label) : $label;
    }

    public function render(string $selectedValue): string
    {
        $attributes = [
            'option',
            'value="' . HtmlEncoder::encode(value: $this->identifier) . '"',
        ];
        if ($this->identifier === $selectedValue) {
            $attributes[] = 'selected';
        }

        return '<' . implode(separator: ' ', array: $attributes) . '>' . $this->label->render() . '</option>';
    }
}
