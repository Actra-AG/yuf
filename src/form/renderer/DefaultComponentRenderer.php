<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\FormComponent;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;

class DefaultComponentRenderer extends FormRenderer
{
    public function __construct(private readonly FormComponent $formComponent)
    {
    }

    public function prepare(): void
    {
        $componentTag = new HtmlTag($this->formComponent->name, false);

        if ($this->formComponent->hasErrors(withChildElements: true)) {
            $componentTag->addHtmlTagAttribute(new HtmlTagAttribute('class', 'has-error', true));
        }
        $this->setHtmlTag($componentTag);
    }
}