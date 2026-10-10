<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form;

use actra\yuf\form\component\FormField;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\html\HtmlText;

abstract class FormRenderer
{
    /**
     * The `<label>` of a field: for the field name, hidden visually (not for screen readers) if the field renders no
     * label, with the required marker and the label info.
     */
    public static function createLabelTag(FormField $formField): HtmlTag
    {
        $labelAttributes = [HtmlTagAttribute::fromText(name: 'for', text: $formField->name)];
        if (!$formField->renderLabel) {
            $labelAttributes[] = HtmlTagAttribute::fromText(name: 'class', text: 'visuallyhidden');
        }

        $labelTag = new HtmlTag(name: 'label', selfClosing: false, htmlTagAttributes: $labelAttributes);
        $labelTag->addText(htmlText: $formField->label);

        if ($formField->isRequired() && $formField->renderRequiredAbbr) {
            $abbrTag = new HtmlTag(name: 'span', selfClosing: false, htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: 'required'),
            ]);
            $abbrTag->addText(htmlText: HtmlText::fromHtml(html: '*'));
            $labelTag->addTag(htmlTag: $abbrTag);
        }

        $labelInfoText = $formField->labelInfoText;
        if ($labelInfoText !== null) {
            $labelInfoTag = new HtmlTag(name: 'i', selfClosing: false, htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: 'label-info'),
            ]);
            $labelInfoTag->addText(htmlText: $labelInfoText);
            $labelTag->addTag(htmlTag: $labelInfoTag);
        }

        return $labelTag;
    }

    public static function addErrorsToParentHtmlTag(
        FormComponent $formComponentWithErrors,
        HtmlTag $parentHtmlTag,
    ): void {
        if (!$formComponentWithErrors->hasErrors(withChildElements: false)) {
            return;
        }
        $divTag = new HtmlTag(
            name: 'div',
            selfClosing: false,
            htmlTagAttributes: [
                HtmlTagAttribute::fromText(name: 'class', text: 'form-input-error'),
                HtmlTagAttribute::fromText(name: 'id', text: $formComponentWithErrors->name . '-error'),
                HtmlTagAttribute::fromText(name: 'role', text: 'alert'),
                HtmlTagAttribute::fromText(name: 'aria-live', text: 'assertive'),
            ],
        );
        $errorsHtml = [];
        foreach ($formComponentWithErrors->errorCollection->listErrors() as $htmlText) {
            $errorsHtml[] = $htmlText->render();
        }
        $divTag->addText(htmlText: HtmlText::fromHtml(html: implode(separator: '<br>', array: $errorsHtml)));
        $parentHtmlTag->addTag(htmlTag: $divTag);
    }

    public static function addFieldInfoToParentHtmlTag(FormField $formFieldWithFieldInfo, HtmlTag $parentHtmlTag): void
    {
        $fieldInfo = $formFieldWithFieldInfo->fieldInfo;
        if ($fieldInfo === null) {
            return;
        }
        $divTag = new HtmlTag(name: 'div', selfClosing: false, htmlTagAttributes: [
            HtmlTagAttribute::fromText(name: 'class', text: 'form-input-info'),
            HtmlTagAttribute::fromText(name: 'id', text: $formFieldWithFieldInfo->name . '-info'),
        ]);
        $divTag->addText(htmlText: $fieldInfo);
        $parentHtmlTag->addTag(htmlTag: $divTag);
    }

    public static function addAriaAttributesToHtmlTag(FormField $formField, HtmlTag $parentHtmlTag): void
    {
        $ariaDescribedBy = [];
        if ($formField->hasErrors(withChildElements: false)) {
            $parentHtmlTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'aria-invalid', text: 'true'),
            );
            $ariaDescribedBy[] = $formField->name . '-error';
        }
        if ($formField->fieldInfo !== null) {
            $ariaDescribedBy[] = $formField->name . '-info';
        }
        if (count(value: $ariaDescribedBy) > 0) {
            $parentHtmlTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'aria-describedby',
                    text: implode(separator: ' ', array: $ariaDescribedBy),
                ),
            );
        }
    }

    /**
     * Creates the base Tag-Element of the component, including its child elements. The renderer keeps no state: every
     * call builds and returns a new tag, so a component can be rendered more than once.
     */
    abstract public function createHtmlTag(): HtmlTag;
}
