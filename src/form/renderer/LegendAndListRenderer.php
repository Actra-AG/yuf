<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\OptionsField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;
use Override;

final class LegendAndListRenderer extends FormRenderer
{
    public function __construct(private readonly OptionsField $optionsField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $optionsField = $this->optionsField;
        $fieldsetTag = LegendAndListRenderer::createFieldsetTag(optionsField: $optionsField);
        $fieldsetTag->addTag(htmlTag: LegendAndListRenderer::createLegendTag(optionsField: $optionsField));
        $listDescription = $optionsField->listDescription;
        if ($listDescription !== null) {
            $fieldsetTag->addText(
                htmlText: HtmlText::fromHtml(
                    html: '<div class="fieldset-info">' . $listDescription->render() . '</div>',
                ),
            );
        }
        $defaultFormFieldRenderer = $optionsField->getDefaultRenderer();
        $fieldsetTag->addTag(htmlTag: $defaultFormFieldRenderer->createHtmlTag());
        FormRenderer::addErrorsToParentHtmlTag(
            formComponentWithErrors: $optionsField,
            parentHtmlTag: $fieldsetTag,
        );
        if ($optionsField->fieldInfo !== null) {
            FormRenderer::addFieldInfoToParentHtmlTag(
                formFieldWithFieldInfo: $optionsField,
                parentHtmlTag: $fieldsetTag,
            );
        }
        return $fieldsetTag;
    }

    public static function createFieldsetTag(OptionsField $optionsField): HtmlTag
    {
        $fieldsetTag = new HtmlTag(
            name: 'fieldset',
            selfClosing: false,
            htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: 'legend-and-list'),
            ],
        );
        FormRenderer::addAriaAttributesToHtmlTag(formField: $optionsField, parentHtmlTag: $fieldsetTag);

        return $fieldsetTag;
    }

    public static function createLegendTag(OptionsField $optionsField): HtmlTag
    {
        $legendAttributes = [];
        if (!$optionsField->renderLabel) {
            $legendAttributes[] = HtmlTagAttribute::fromText(name: 'class', text: 'visuallyhidden');
        }
        $labelText = $optionsField->label;
        $labelInfoText = $optionsField->labelInfoText;
        if ($labelInfoText !== null) {
            // Add a space to separate it from the following labelInfo-Tag
            $labelText = HtmlText::fromHtml(html: ' ' . $labelText->render());
        }
        $legendTag = new HtmlTag(
            name: 'legend',
            selfClosing: false,
            htmlTagAttributes: $legendAttributes,
        );
        $legendTag->addText(htmlText: $labelText);
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
        if (
            $optionsField->isRequired()
            && $optionsField->renderRequiredAbbr
        ) {
            $spanTag = new HtmlTag(
                name: 'span',
                selfClosing: false,
                htmlTagAttributes: [
                    HtmlTagAttribute::fromText(name: 'class', text: 'required'),
                ],
            );
            $spanTag->addText(htmlText: HtmlText::fromHtml(html: '*'));
            $legendTag->addTag(htmlTag: $spanTag);
        }
        return $legendTag;
    }
}
