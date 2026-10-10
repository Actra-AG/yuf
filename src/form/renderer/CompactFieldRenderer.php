<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

/**
 * Renders a field as its label followed by its control in one `<div class="form-compact-field">` (`has-error` is
 * added for a field with errors), for search and filter forms. Unlike `DefinitionListRenderer` there is no `<dl>`,
 * `<dt>` and `<dd>`, no additional column and no extra container for the control. Errors and the field info are
 * rendered after the control as usual, only when the field has them (the control refers to them with
 * `aria-describedby`).
 *
 * Use it for one field with `setRenderer()` or for all fields of a form with `Form::useCompactFieldRenderer()`.
 */
final class CompactFieldRenderer extends FormRenderer
{
    public function __construct(private readonly FormField $formField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $formField = $this->formField;
        $divTag = new HtmlTag(name: 'div', selfClosing: false, htmlTagAttributes: [
            HtmlTagAttribute::fromText(
                name: 'class',
                text: $formField->hasErrors(withChildElements: true)
                    ? 'form-compact-field has-error'
                    : 'form-compact-field',
            ),
        ]);
        $divTag->addTag(htmlTag: FormRenderer::createLabelTag(formField: $formField));
        $divTag->addTag(htmlTag: $formField->getDefaultRenderer()->createHtmlTag());
        FormRenderer::addErrorsToParentHtmlTag(formComponentWithErrors: $formField, parentHtmlTag: $divTag);
        FormRenderer::addFieldInfoToParentHtmlTag(formFieldWithFieldInfo: $formField, parentHtmlTag: $divTag);

        return $divTag;
    }
}
