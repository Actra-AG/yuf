<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\FormCollection;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;

class DefaultCollectionRenderer extends FormRenderer
{

    public function __construct(private readonly FormCollection $formCollection)
    {
    }

    public function prepare(): void
    {
        $componentTag = new HtmlTag($this->formCollection->name, false);

        if ($this->formCollection->hasErrors(withChildElements: true)) {
            $componentTag->addHtmlTagAttribute(new HtmlTagAttribute('class', 'has-error', true));
        }

        foreach ($this->formCollection->childComponents as $childComponent) {
            $componentTag->addTag($childComponent->getHtmlTag());
        }

        $this->setHtmlTag($componentTag);
    }
}