<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\OptionsField;
use actra\yuf\form\component\field\ToggleChildren;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;
use Override;

/**
 * The markup of `ToggleField` and `MultiToggleField`: a list of radio buttons or checkboxes, each with the child
 * components of its option, optionally inside a fieldset with a legend.
 *
 * @internal
 */
final class ToggleFieldRenderer extends FormRenderer
{
    public function __construct(
        private readonly OptionsField $toggleField,
        private readonly ToggleChildren $toggleChildren,
        private readonly bool $displayLegend,
    ) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $ulTag = $this->createListTag();
        foreach ($this->toggleField->formOptions->getItems() as $option) {
            $ulTag->addTag(htmlTag: $this->createOptionTag(key: $option->key, htmlText: $option->htmlText));
        }

        return $this->displayLegend ? $this->wrapInFieldset(ulTag: $ulTag) : $this->wrapInDiv(ulTag: $ulTag);
    }

    private function createListTag(): HtmlTag
    {
        $ulTagClasses = ['form-toggle-list'];
        if ($this->toggleField->hasErrors(withChildElements: false)) {
            $ulTagClasses[] = 'list-has-error';
        }

        return new HtmlTag(
            name: 'ul',
            selfClosing: false,
            htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: implode(separator: ' ', array: $ulTagClasses)),
            ],
        );
    }

    private function createOptionTag(string $key, HtmlText $htmlText): HtmlTag
    {
        $combinedSpecifier = $this->toggleField->name . '_' . $key;
        $spanLabelTag = new HtmlTag(
            name: 'span',
            selfClosing: false,
            htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: 'label-text'),
            ],
        );
        $spanLabelTag->addText(htmlText: $htmlText);
        $labelTag = new HtmlTag(name: 'label', selfClosing: false);
        $labelTag->addTag(htmlTag: $this->createInputTag(key: $key, combinedSpecifier: $combinedSpecifier));
        $labelTag->addText(htmlText: HtmlText::fromHtml(html: ' ' . $spanLabelTag->render()));
        $liTag = new HtmlTag(name: 'li', selfClosing: false);
        $liTag->addTag(htmlTag: $labelTag);
        if ($this->toggleChildren->has(mainOption: $key)) {
            $liTag->addTag(htmlTag: $this->createChildrenTag(key: $key, combinedSpecifier: $combinedSpecifier));
        }

        return $liTag;
    }

    private function createInputTag(string $key, string $combinedSpecifier): HtmlTag
    {
        $isMultiple = $this->toggleField->isMultiple();
        $inputAttributes = [
            HtmlTagAttribute::fromText(name: 'type', text: $isMultiple ? 'checkbox' : 'radio'),
            HtmlTagAttribute::fromText(name: 'toggle-id', text: $combinedSpecifier),
            HtmlTagAttribute::fromText(
                name: 'name',
                text: $isMultiple ? $this->toggleField->name . '[]' : $this->toggleField->name,
            ),
            HtmlTagAttribute::fromText(name: 'value', text: $key),
        ];
        if ($this->toggleChildren->has(mainOption: $key)) {
            $inputAttributes[] = HtmlTagAttribute::fromText(name: 'aria-describedby', text: $combinedSpecifier);
        }
        if ($this->toggleField->isSelected(optionKey: $key)) {
            $inputAttributes[] = HtmlTagAttribute::fromName(name: 'checked');
        }

        return new HtmlTag(name: 'input', selfClosing: true, htmlTagAttributes: $inputAttributes);
    }

    private function createChildrenTag(string $key, string $combinedSpecifier): HtmlTag
    {
        $divTag = new HtmlTag(
            name: 'div',
            selfClosing: false,
            htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: 'form-toggle-content'),
                HtmlTagAttribute::fromText(name: 'id', text: $combinedSpecifier),
            ],
        );
        foreach ($this->toggleChildren->getForMainOption(mainOption: $key) as $childComponent) {
            if ($childComponent->getRenderer() === null) {
                $childComponent->setRenderer(
                    renderer: $this->toggleChildren->createDefaultChildRenderer(childComponent: $childComponent),
                );
            }
            $divTag->addTag(htmlTag: $childComponent->getHtmlTag());
        }

        return $divTag;
    }

    /**
     * The list without a legend of its own (the label is rendered by the surrounding renderer).
     */
    private function wrapInDiv(HtmlTag $ulTag): HtmlTag
    {
        $divClasses = ['form-element'];
        if ($this->toggleField->hasErrors(withChildElements: true)) {
            $divClasses[] = 'has-error';
        }
        $divTag = new HtmlTag(
            name: 'div',
            selfClosing: false,
            htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: implode(separator: ' ', array: $divClasses)),
            ],
        );
        $divTag->addTag(htmlTag: $ulTag);
        FormRenderer::addErrorsToParentHtmlTag(formComponentWithErrors: $this->toggleField, parentHtmlTag: $divTag);

        return $divTag;
    }

    private function wrapInFieldset(HtmlTag $ulTag): HtmlTag
    {
        $fieldsetTag = LegendAndListRenderer::createFieldsetTag(optionsField: $this->toggleField);
        $fieldsetTag->addTag(htmlTag: $this->createLegendTag());
        $listDescription = $this->toggleField->listDescription;
        if ($listDescription !== null) {
            $fieldsetTag->addText(
                htmlText: HtmlText::fromHtml(
                    html: '<div class="fieldset-info">' . $listDescription->render() . '</div>',
                ),
            );
        }
        $fieldsetTag->addTag(htmlTag: $ulTag);
        FormRenderer::addErrorsToParentHtmlTag(
            formComponentWithErrors: $this->toggleField,
            parentHtmlTag: $fieldsetTag,
        );

        return $fieldsetTag;
    }

    /**
     * Like the legend of `LegendAndListRenderer`, but the required mark comes before the label info (as always in the
     * toggle markup).
     */
    private function createLegendTag(): HtmlTag
    {
        $legendTag = new HtmlTag(
            name: 'legend',
            selfClosing: false,
            htmlTagAttributes: $this->toggleField->renderLabel ? [] : [
                HtmlTagAttribute::fromText(name: 'class', text: 'visuallyhidden'),
            ],
        );
        $legendTag->addText(htmlText: $this->toggleField->label);
        if ($this->toggleField->isRequired() && $this->toggleField->renderRequiredAbbr) {
            $requiredTag = new HtmlTag(
                name: 'span',
                selfClosing: false,
                htmlTagAttributes: [
                    HtmlTagAttribute::fromText(name: 'class', text: 'required'),
                ],
            );
            $requiredTag->addText(htmlText: HtmlText::fromHtml(html: '*'));
            $legendTag->addTag(htmlTag: $requiredTag);
        }
        $labelInfoText = $this->toggleField->labelInfoText;
        if ($labelInfoText !== null) {
            $labelInfoTag = new HtmlTag(
                name: 'i',
                selfClosing: false,
                htmlTagAttributes: [
                    HtmlTagAttribute::fromText(name: 'class', text: 'legend-info'),
                ],
            );
            $labelInfoTag->addText(htmlText: $labelInfoText);
            $legendTag->addTag(htmlTag: $labelInfoTag);
        }

        return $legendTag;
    }
}
