<?php
/**
 * @author    Christof Moser <contact@actra.ch>
 * @copyright Actra AG, Embrach, Switzerland, www.actra.ch
 * @license   MIT
 */

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\HiddenField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;

class HiddenFieldRenderer extends FormRenderer
{
    public function __construct(private readonly HiddenField $hiddenField)
    {
    }

    public function prepare(): void
    {
        $hiddenField = $this->hiddenField;
        $this->setHtmlTag(htmlTag: new HtmlTag(name: 'input', selfClosing: true, htmlTagAttributes: [
            new HtmlTagAttribute(name: 'type', value: $hiddenField->inputType->value, valueIsEncodedForRendering: true),
            new HtmlTagAttribute(name: 'name', value: $hiddenField->name, valueIsEncodedForRendering: true),
            new HtmlTagAttribute(name: 'value', value: $hiddenField->renderValue(), valueIsEncodedForRendering: true),
        ]));
    }
}