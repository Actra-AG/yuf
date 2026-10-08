<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\InputField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

/**
 * Renders an input field. Extension point: `NumericFieldRenderer` extends it; a project can do the same to add
 * attributes to the input tag (`prepare()` and `getHtmlTag()`).
 */
class InputFieldRenderer extends FormRenderer
{
    public function __construct(private readonly InputField $formField) {}

    #[Override]
    public function prepare(): void
    {
        $formField = $this->formField;
        $inputTag = new HtmlTag(name: 'input', selfClosing: true);
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'type', text: $formField->inputType->value),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'name', text: $formField->name),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'id', text: $formField->id),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromHtml(name: 'value', html: $formField->renderValue()),
        );
        if ($formField->placeholder !== null) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'placeholder', text: $formField->placeholder),
            );
        }
        if ($formField->autoComplete !== null) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'autocomplete',
                    text: $formField->autoComplete->value,
                ),
            );
        }
        if ($formField->autoFocus) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromName(name: 'autofocus'),
            );
        }
        if ($formField->maxLength !== null) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'maxlength', text: $formField->maxLength),
            );
        }
        FormRenderer::addAriaAttributesToHtmlTag(
            formField: $formField,
            parentHtmlTag: $inputTag,
        );
        $this->setHtmlTag(htmlTag: $inputTag);
    }
}
