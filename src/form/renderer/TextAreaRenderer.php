<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\TextAreaField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;
use Override;

final class TextAreaRenderer extends FormRenderer
{
    public function __construct(private readonly TextAreaField $textAreaField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $textAreaField = $this->textAreaField;
        $textareaTag = new HtmlTag(
            name: 'textarea',
            selfClosing: false,
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'name', text: $textAreaField->name),
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'id', text: $textAreaField->id),
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'rows', text: (string) $textAreaField->rows),
        );
        $textareaTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'cols', text: (string) $textAreaField->cols),
        );
        $cssClassesForRenderer = $textAreaField->cssClassesForRenderer;
        if (count(value: $cssClassesForRenderer) > 0) {
            $textareaTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'class',
                    text: implode(separator: ' ', array: $cssClassesForRenderer),
                ),
            );
        }
        $placeholder = $textAreaField->getPlaceholder();
        if ($placeholder !== null) {
            $textareaTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'placeholder', text: $placeholder),
            );
        }
        if ($textAreaField->autoFocus) {
            $textareaTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromName(name: 'autofocus'),
            );
        }
        FormRenderer::addAriaAttributesToHtmlTag(
            formField: $textAreaField,
            parentHtmlTag: $textareaTag,
        );
        $textareaTag->addText(htmlText: HtmlText::fromHtml(html: $textAreaField->renderValue()));
        return $textareaTag;
    }
}
