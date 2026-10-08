<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\FormField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;
use Override;

final class DefinitionListRenderer extends FormRenderer
{
    /** @var list<HtmlTag> */
    private array $htmlTagsBeforeFormField = [];

    public function __construct(private readonly FormField $formField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $formField = $this->formField;
        $labelAttributes = [HtmlTagAttribute::fromText(name: 'for', text: $formField->name)];
        if (!$this->formField->renderLabel) {
            $labelAttributes[] = HtmlTagAttribute::fromText(name: 'class', text: 'visuallyhidden');
        }

        $labelTag = new HtmlTag('label', false, $labelAttributes);
        $labelTag->addText($formField->label);

        if ($formField->isRequired() && $formField->renderRequiredAbbr) {
            $abbrTag = new HtmlTag('span', false, [
                HtmlTagAttribute::fromText(name: 'class', text: 'required'),
            ]);
            $abbrTag->addText(HtmlText::fromHtml('*'));
            $labelTag->addTag($abbrTag);
        }

        $labelInfoText = $formField->labelInfoText;
        if ($labelInfoText !== null) {
            $labelInfoTag = new HtmlTag('i', false, [
                HtmlTagAttribute::fromText(name: 'class', text: 'label-info'),
            ]);
            $labelInfoTag->addText($labelInfoText);
            $labelTag->addTag($labelInfoTag);
        }

        if (!$this->formField->renderLabel) {
            // A <div> (instead of <dd>) will be created to contain the child with the "visualInvisible" <label>
            $divTag = new HtmlTag('div', false);
            $divTag->addTag($labelTag);
            if ($formField->hasErrors(withChildElements: true)) {
                $divTag->addHtmlTagAttribute(HtmlTagAttribute::fromText(
                    name: 'class',
                    text: 'form-toggle-content-item has-error',
                ));
            } else {
                $divTag->addHtmlTagAttribute(HtmlTagAttribute::fromText(
                    name: 'class',
                    text: 'form-toggle-content-item',
                ));
            }
            $defaultFormFieldRenderer = $formField->getDefaultRenderer();
            $divTag->addTag($defaultFormFieldRenderer->createHtmlTag());

            FormRenderer::addErrorsToParentHtmlTag($formField, $divTag);
            if ($formField->fieldInfo !== null) {
                FormRenderer::addFieldInfoToParentHtmlTag($formField, $divTag);
            }
            return $divTag;
        }

        // Show WITH label, therefore <dl><dt><dd>-Frame is required:
        $dtTag = new HtmlTag('dt', false);

        $dtTag->addTag($labelTag);

        $additionalColumnContent = $formField->additionalColumnContent;

        $ddClasses = [];

        if ($additionalColumnContent !== null) {
            $ddClasses[] = 'form-cols';
        }

        if ($formField->hasErrors(withChildElements: true)) {
            $ddClasses[] = 'has-error';
        }

        $ddAttributes = (count($ddClasses) === 0) ? [] : [
            HtmlTagAttribute::fromText(name: 'class', text: implode(separator: ' ', array: $ddClasses)),
        ];
        $ddTag = new HtmlTag('dd', false, $ddAttributes);

        foreach ($this->htmlTagsBeforeFormField as $htmlTag) {
            $ddTag->addTag($htmlTag);
        }

        $defaultFormFieldRenderer = $formField->getDefaultRenderer();
        $fieldTag = $defaultFormFieldRenderer->createHtmlTag();

        if ($additionalColumnContent !== null) {
            $column1 = new HtmlTag('div', false, [HtmlTagAttribute::fromText(name: 'class', text: 'form-col-1')]);
            $column1->addTag($fieldTag);
            $ddTag->addTag($column1);

            $column2 = new HtmlTag('div', false, [HtmlTagAttribute::fromText(name: 'class', text: 'form-col-2')]);
            $column2->addText($additionalColumnContent);
            $ddTag->addTag($column2);
        } else {
            $ddTag->addTag($fieldTag);
        }

        FormRenderer::addErrorsToParentHtmlTag($formField, $ddTag);

        if ($formField->fieldInfo !== null) {
            FormRenderer::addFieldInfoToParentHtmlTag($formField, $ddTag);
        }

        $dlTag = new HtmlTag('dl', false);
        $dlTag->addTag($dtTag);
        $dlTag->addTag($ddTag);
        return $dlTag;
    }

    public function addHtmlTagBeforeFormField(HtmlTag $htmlTag): void
    {
        $this->htmlTagsBeforeFormField[] = $htmlTag;
    }
}
