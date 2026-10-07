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

class FormControlRenderer extends FormRenderer
{
    public function __construct(private readonly FormControl $formControl) {}

    #[Override]
    public function prepare(): void
    {
        $formControl = $this->formControl;

        $buttonTag = new HtmlTag('button', false, [
            new HtmlTagAttribute('type', 'submit', true),
            new HtmlTagAttribute('name', $formControl->name, true),
        ]);
        $buttonTag->addText(htmlText: $formControl->submitLabel);

        $divTag = new HtmlTag('div', false, [new HtmlTagAttribute('class', 'form-control', true)]);
        $divTag->addTag($buttonTag);

        if ($formControl->cancelLink !== null) {
            $aTag = new HtmlTag('a', false, [
                new HtmlTagAttribute('href', $formControl->cancelLink, true),
                new HtmlTagAttribute('class', 'link-cancel', true),
            ]);
            $aTag->addText($formControl->cancelLabel);
            $divTag->addTag($aTag);
        }

        $this->setHtmlTag($divTag);
    }
}
