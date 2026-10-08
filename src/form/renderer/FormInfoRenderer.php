<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\FormInfo;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

final class FormInfoRenderer extends FormRenderer
{
    public function __construct(private readonly FormInfo $formInfo) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $formInfo = $this->formInfo;

        $dtTag = new HtmlTag('dt', false);
        $dtClasses = $formInfo->dtClasses;
        if (count($dtClasses) > 0) {
            $dtTag->addHtmlTagAttribute(
                HtmlTagAttribute::fromText(name: 'class', text: implode(separator: ' ', array: $dtClasses)),
            );
        }
        $dtTag->addText($formInfo->title);

        $ddTag = new HtmlTag('dd', false);
        $ddClasses = $formInfo->ddClasses;
        if (count($ddClasses) > 0) {
            $ddTag->addHtmlTagAttribute(
                HtmlTagAttribute::fromText(name: 'class', text: implode(separator: ' ', array: $ddClasses)),
            );
        }
        $ddTag->addText($formInfo->content);

        $dlTag = new HtmlTag('dl', false);
        $dlClasses = $formInfo->dlClasses;
        if (count($dlClasses) > 0) {
            $dlTag->addHtmlTagAttribute(
                HtmlTagAttribute::fromText(name: 'class', text: implode(separator: ' ', array: $dlClasses)),
            );
        }
        $dlTag->addTag($dtTag);
        $dlTag->addTag($ddTag);

        return $dlTag;
    }
}
