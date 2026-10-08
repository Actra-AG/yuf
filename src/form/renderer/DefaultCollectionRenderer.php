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
use Override;

final class DefaultCollectionRenderer extends FormRenderer
{
    public function __construct(private readonly FormCollection $formCollection) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $componentTag = new HtmlTag($this->formCollection->name, false);

        if ($this->formCollection->hasErrors(withChildElements: true)) {
            $componentTag->addHtmlTagAttribute(HtmlTagAttribute::fromText(name: 'class', text: 'has-error'));
        }

        foreach ($this->formCollection->childComponents as $childComponent) {
            $componentTag->addTag($childComponent->getHtmlTag());
        }

        return $componentTag;
    }
}
