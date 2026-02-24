<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;

class CheckboxItemRenderer extends FormRenderer
{
    public function __construct(private readonly CheckboxOptionsField $checkboxOptionsField)
    {
    }

    public function prepare(): void
    {
        $checkboxOptionsField = $this->checkboxOptionsField;

        $divFormCheck = new HtmlTag(
            name: 'div',
            selfClosing: false
        );
        $formItemCheckboxClasses = ['form-check'];
        if ($checkboxOptionsField->hasErrors(withChildElements: true)) {
            $formItemCheckboxClasses[] = 'has-error';
        }
        $divFormCheck->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'class',
                value: implode(
                    separator: ' ',
                    array: $formItemCheckboxClasses
                ),
                valueIsEncodedForRendering: true
            )
        );
        $divFormCheck->addTag(htmlTag: $this->getInputTag());
        $labelTag = new HtmlTag(
            name: 'label',
            selfClosing: false
        );
        $labelTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'for',
                value: $this->checkboxOptionsField->id,
                valueIsEncodedForRendering: true
            )
        );
        $labelTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'class',
                value: 'form-check-label',
                valueIsEncodedForRendering: true
            )
        );
        $labelTag->addText(htmlText: $checkboxOptionsField->label);
        $divFormCheck->addTag(htmlTag: $labelTag);
        if (!is_null(value: $checkboxOptionsField->fieldInfo)) {
            FormRenderer::addFieldInfoToParentHtmlTag(
                formFieldWithFieldInfo: $checkboxOptionsField,
                parentHtmlTag: $divFormCheck
            );
        }
        FormRenderer::addErrorsToParentHtmlTag(
            formComponentWithErrors: $checkboxOptionsField,
            parentHtmlTag: $divFormCheck
        );
        $this->setHtmlTag(htmlTag: $divFormCheck);
    }

    private function getInputTag(): HtmlTag
    {
        $inputTag = new HtmlTag(
            name: 'input',
            selfClosing: true
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'type',
                value: 'checkbox',
                valueIsEncodedForRendering: true
            )
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'name',
                value: $this->checkboxOptionsField->name,
                valueIsEncodedForRendering: true
            )
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'id',
                value: $this->checkboxOptionsField->id,
                valueIsEncodedForRendering: true
            )
        );
        $options = $this->checkboxOptionsField->formOptions->data;
        $optionValue = key(array: $options);
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'value',
                value: (string)$optionValue,
                valueIsEncodedForRendering: true
            )
        );
        $checkboxValue = $this->checkboxOptionsField->getRawValue();
        if (
            !is_null(value: $checkboxValue)
            && $checkboxValue !== []
            && (string)$checkboxValue[0] == $optionValue
        ) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'checked',
                    value: null,
                    valueIsEncodedForRendering: true
                )
            );
        }
        $ariaDescribedBy = [];
        if ($this->checkboxOptionsField->hasErrors(withChildElements: true)) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'aria-invalid',
                    value: 'true',
                    valueIsEncodedForRendering: true
                )
            );
            $ariaDescribedBy[] = $this->checkboxOptionsField->name . '-error';
        }
        if (!is_null(value: $this->checkboxOptionsField->fieldInfo)) {
            $ariaDescribedBy[] = $this->checkboxOptionsField->name . '-info';
        }
        if (count(value: $ariaDescribedBy) > 0) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'aria-describedby',
                    value: implode(
                        separator: ' ',
                        array: $ariaDescribedBy
                    ),
                    valueIsEncodedForRendering: true
                )
            );
        }

        return $inputTag;
    }
}