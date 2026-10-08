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
 * The hidden input of `HiddenField` and `CsrfTokenField`.
 *
 * @internal
 */
final class HiddenFieldRenderer extends FormRenderer
{
    public function __construct(private readonly InputField $hiddenField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $hiddenField = $this->hiddenField;

        return new HtmlTag(name: 'input', selfClosing: true, htmlTagAttributes: [
            HtmlTagAttribute::fromText(name: 'type', text: $hiddenField->inputType->value),
            HtmlTagAttribute::fromText(name: 'name', text: $hiddenField->name),
            HtmlTagAttribute::fromHtml(name: 'value', html: $hiddenField->renderValue()),
        ]);
    }
}
