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
 */
class ToggleFieldRenderer extends FormRenderer
{
    public function __construct(
        private readonly OptionsField $toggleField,
        private readonly ToggleChildren $toggleChildren,
        private readonly bool $displayLegend,
    ) {}

    #[Override]
    public function prepare(): void
    {
        $ulTag = $this->createListTag();
        foreach ($this->toggleField->formOptions->data as $key => $htmlText) {
            $ulTag->addTag(htmlTag: $this->createOptionTag(key: (string) $key, htmlText: $htmlText));
        }
        $this->setHtmlTag(
            htmlTag: $this->displayLegend ? $this->wrapInFieldset(ulTag: $ulTag) : $this->wrapInDiv(ulTag: $ulTag),
        );
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
                new HtmlTagAttribute(
                    name: 'class',
                    value: implode(separator: ' ', array: $ulTagClasses),
                    valueIsEncodedForRendering: true,
                ),
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
                new HtmlTagAttribute(name: 'class', value: 'label-text', valueIsEncodedForRendering: true),
            ],
        );
        $spanLabelTag->addText(htmlText: $htmlText);
        $labelTag = new HtmlTag(name: 'label', selfClosing: false);
        $labelTag->addTag(htmlTag: $this->createInputTag(key: $key, combinedSpecifier: $combinedSpecifier));
        $labelTag->addText(htmlText: HtmlText::encoded(textContent: ' ' . $spanLabelTag->render()));
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
            new HtmlTagAttribute(
                name: 'type',
                value: $isMultiple ? 'checkbox' : 'radio',
                valueIsEncodedForRendering: true,
            ),
            new HtmlTagAttribute(name: 'toggle-id', value: $combinedSpecifier, valueIsEncodedForRendering: true),
            new HtmlTagAttribute(
                name: 'name',
                value: $isMultiple ? $this->toggleField->name . '[]' : $this->toggleField->name,
                valueIsEncodedForRendering: true,
            ),
            new HtmlTagAttribute(name: 'value', value: $key, valueIsEncodedForRendering: true),
        ];
        if ($this->toggleChildren->has(mainOption: $key)) {
            $inputAttributes[] = new HtmlTagAttribute(
                name: 'aria-describedby',
                value: $combinedSpecifier,
                valueIsEncodedForRendering: true,
            );
        }
        if ($this->toggleField->isSelected(optionKey: $key)) {
            $inputAttributes[] = new HtmlTagAttribute(name: 'checked', value: null, valueIsEncodedForRendering: true);
        }

        return new HtmlTag(name: 'input', selfClosing: true, htmlTagAttributes: $inputAttributes);
    }

    private function createChildrenTag(string $key, string $combinedSpecifier): HtmlTag
    {
        $divTag = new HtmlTag(
            name: 'div',
            selfClosing: false,
            htmlTagAttributes: [
                new HtmlTagAttribute(name: 'class', value: 'form-toggle-content', valueIsEncodedForRendering: true),
                new HtmlTagAttribute(name: 'id', value: $combinedSpecifier, valueIsEncodedForRendering: true),
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
                new HtmlTagAttribute(
                    name: 'class',
                    value: implode(separator: ' ', array: $divClasses),
                    valueIsEncodedForRendering: true,
                ),
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
                htmlText: HtmlText::encoded(
                    textContent: '<div class="fieldset-info">' . $listDescription->render() . '</div>',
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
                new HtmlTagAttribute(name: 'class', value: 'visuallyhidden', valueIsEncodedForRendering: true),
            ],
        );
        $legendTag->addText(htmlText: $this->toggleField->label);
        if ($this->toggleField->isRequired() && $this->toggleField->renderRequiredAbbr) {
            $requiredTag = new HtmlTag(
                name: 'span',
                selfClosing: false,
                htmlTagAttributes: [
                    new HtmlTagAttribute(name: 'class', value: 'required', valueIsEncodedForRendering: true),
                ],
            );
            $requiredTag->addText(htmlText: HtmlText::encoded(textContent: '*'));
            $legendTag->addTag(htmlTag: $requiredTag);
        }
        $labelInfoText = $this->toggleField->labelInfoText;
        if ($labelInfoText !== null) {
            $labelInfoTag = new HtmlTag(
                name: 'i',
                selfClosing: false,
                htmlTagAttributes: [
                    new HtmlTagAttribute(name: 'class', value: 'legend-info', valueIsEncodedForRendering: true),
                ],
            );
            $labelInfoTag->addText(htmlText: $labelInfoText);
            $legendTag->addTag(htmlTag: $labelInfoTag);
        }

        return $legendTag;
    }
}
