<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\FormControl;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

final class FormControlRenderer extends FormRenderer
{
    public function __construct(private readonly FormControl $formControl) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $formControl = $this->formControl;

        $buttonTag = new HtmlTag('button', false, [
            HtmlTagAttribute::fromText(name: 'type', text: 'submit'),
            HtmlTagAttribute::fromText(name: 'name', text: $formControl->name),
        ]);
        $buttonTag->addText(htmlText: $formControl->submitLabel);

        $divTag = new HtmlTag('div', false, [HtmlTagAttribute::fromText(name: 'class', text: 'form-control')]);
        $divTag->addTag($buttonTag);

        if ($formControl->cancelLink !== null) {
            $aTag = new HtmlTag('a', false, [
                HtmlTagAttribute::fromText(name: 'href', text: $formControl->cancelLink),
                HtmlTagAttribute::fromText(name: 'class', text: 'link-cancel'),
            ]);
            $aTag->addText($formControl->cancelLabel);
            $divTag->addTag($aTag);
        }

        return $divTag;
    }
}
