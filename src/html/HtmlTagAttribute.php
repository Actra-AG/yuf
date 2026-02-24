<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

class HtmlTagAttribute extends HtmlElement
{
    public null|string|int $value;
    private bool $valueIsEncodedForRendering;

    public function __construct(
        string          $name,
        null|string|int $value,
        bool            $valueIsEncodedForRendering
    )
    {
        $this->value = $value;
        $this->valueIsEncodedForRendering = $valueIsEncodedForRendering;
        parent::__construct($name);
    }

    /**
     * Generate the html-code for this Attribute-Element to be used for output
     *
     * @return string : Generated html-code
     */
    public function render(): string
    {
        if (is_null($this->value)) {
            return $this->name;
        }

        $renderValue = $this->valueIsEncodedForRendering ? (string)$this->value : HtmlEncoder::encode(value: $this->value);

        return $this->name . '="' . $renderValue . '"';
    }
}