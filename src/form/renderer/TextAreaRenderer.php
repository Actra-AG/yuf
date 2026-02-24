<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlEncoder;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;

class TextAreaRenderer extends FormRenderer
{
    public function __construct(private readonly TextAreaField $textAreaField)
    {
    }

    public function prepare(): void
    {
        $textAreaField = $this->textAreaField;
        $textareaTag = new HtmlTag(
            name: 'textarea',
            selfClosing: false
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'name',
                value: $textAreaField->name,
                valueIsEncodedForRendering: true
            )
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'id',
                value: $textAreaField->id,
                valueIsEncodedForRendering: true
            )
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'rows',
                value: $textAreaField->rows,
                valueIsEncodedForRendering: true
            )
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'cols',
                value: $textAreaField->cols,
                valueIsEncodedForRendering: true
            )
        );
        $cssClassesForRenderer = $textAreaField->cssClassesForRenderer;
        if (count(value: $cssClassesForRenderer) > 0) {
            $textareaTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'class',
                    value: implode(separator: ' ', array: $cssClassesForRenderer),
                    valueIsEncodedForRendering: true
                )
            );
        }
        if (!is_null(value: $textAreaField->getPlaceholder())) {
            $textareaTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'placeholder',
                    value: $textAreaField->getPlaceholder(),
                    valueIsEncodedForRendering: true
                )
            );
        }
        if ($textAreaField->autoFocus) {
            $textareaTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'autofocus',
                    value: null,
                    valueIsEncodedForRendering: true
                )
            );
        }
        FormRenderer::addAriaAttributesToHtmlTag(
            formField: $textAreaField,
            parentHtmlTag: $textareaTag
        );
        $value = $textAreaField->getRawValue();
        if (is_array(value: $value)) {
            $rows = [];
            foreach ($value as $row) {
                $rows[] = HtmlEncoder::encode(value: $row);
            }
            $html = implode(separator: PHP_EOL, array: $rows);
        } else {
            $html = HtmlEncoder::encode(value: $value);
        }
        $textareaTag->addText(htmlText: HtmlText::encoded(textContent: $html));
        $this->setHtmlTag(htmlTag: $textareaTag);
    }
}