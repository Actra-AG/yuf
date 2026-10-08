<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\collection\Form;
use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

final class DefaultFormRenderer extends FormRenderer
{
    public function __construct(private readonly Form $form) {}

    #[Override]
    public function prepare(): void
    {
        $form = $this->form;
        $attributes = [
            HtmlTagAttribute::fromText(name: 'method', text: ($form->methodPost ? 'post' : 'get')),
            HtmlTagAttribute::fromText(name: 'action', text: '?' . $form->sentIndicator),
        ];
        $cssClasses = $form->cssClasses;
        if (count(value: $cssClasses) > 0) {
            $attributes[] = HtmlTagAttribute::fromText(
                name: 'class',
                text: implode(separator: ' ', array: $cssClasses),
            );
        }
        if ($form->acceptUpload) {
            $attributes[] = HtmlTagAttribute::fromText(name: 'enctype', text: 'multipart/form-data');
        }
        if ($form->disableClientValidation) {
            $attributes[] = HtmlTagAttribute::fromName(name: 'novalidate');
        }
        $htmlTag = new HtmlTag(name: 'form', selfClosing: false, htmlTagAttributes: $attributes);
        $this->renderErrors(parentTag: $htmlTag);
        foreach ($form->childComponents as $childComponent) {
            $componentRenderer = $childComponent->getRenderer();
            if ($componentRenderer === null) {
                if ($childComponent instanceof FormField) {
                    $childComponentRenderer = $form->getDefaultFormFieldRenderer(formField: $childComponent);
                } else {
                    $childComponentRenderer = $childComponent->getDefaultRenderer();
                }
                $childComponent->setRenderer(renderer: $childComponentRenderer);
            }
            $htmlTag->addTag(htmlTag: $childComponent->getHtmlTag());
        }
        $this->setHtmlTag(htmlTag: $htmlTag);
    }

    private function renderErrors(HtmlTag $parentTag): void
    {
        $form = $this->form;
        if (!$form->hasErrors(withChildElements: true)) {
            return;
        }
        $errorCollection = $form->errorCollection;
        if (!$errorCollection->hasErrors()) {
            return;
        }
        $mainAttributes = [
            HtmlTagAttribute::fromText(name: 'class', text: 'form-error'),
            HtmlTagAttribute::fromText(name: 'role', text: 'alert'),
            HtmlTagAttribute::fromText(name: 'aria-live', text: 'assertive'),
        ];
        if ($errorCollection->count() === 1) {
            $pTag = new HtmlTag(
                name: 'p',
                selfClosing: false,
                htmlTagAttributes: $mainAttributes,
            );
            $strongTag = new HtmlTag(
                name: 'strong',
                selfClosing: false,
            );
            $strongTag->addText(htmlText: $errorCollection->getFirstError());
            $pTag->addTag(htmlTag: $strongTag);
            $parentTag->addTag(htmlTag: $pTag);

            return;
        }
        $parentTag->addTag(
            htmlTag: $divTag = new HtmlTag(
                name: 'div',
                selfClosing: false,
                htmlTagAttributes: $mainAttributes,
            ),
        );
        $ulTag = new HtmlTag(
            name: 'ul',
            selfClosing: false,
        );
        foreach ($errorCollection->listErrors() as $htmlText) {
            $liTag = new HtmlTag(
                name: 'li',
                selfClosing: false,
            );
            $liTag->addText(htmlText: $htmlText);
            $ulTag->addTag(htmlTag: $liTag);
            $divTag->addTag(htmlTag: $ulTag);
        }
    }
}
