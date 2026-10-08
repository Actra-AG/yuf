<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\BooleanField;
use actra\yuf\form\component\field\CheckboxOptionsField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

/**
 * A single checkbox with its label (layout `CHECKBOX_ITEM` of `BooleanField` and `CheckboxOptionsField`).
 *
 * @internal
 */
final class CheckboxItemRenderer extends FormRenderer
{
    public function __construct(private readonly CheckboxOptionsField|BooleanField $checkboxOptionsField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $checkboxOptionsField = $this->checkboxOptionsField;

        $divFormCheck = new HtmlTag(
            name: 'div',
            selfClosing: false,
        );
        $formItemCheckboxClasses = ['form-check'];
        if ($checkboxOptionsField->hasErrors(withChildElements: true)) {
            $formItemCheckboxClasses[] = 'has-error';
        }
        $divFormCheck->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(
                name: 'class',
                text: implode(separator: ' ', array: $formItemCheckboxClasses),
            ),
        );
        $divFormCheck->addTag(htmlTag: $this->getInputTag());
        $labelTag = new HtmlTag(
            name: 'label',
            selfClosing: false,
        );
        $labelTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'for', text: $this->checkboxOptionsField->id),
        );
        $labelTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'class', text: 'form-check-label'),
        );
        $labelTag->addText(htmlText: $checkboxOptionsField->label);
        $divFormCheck->addTag(htmlTag: $labelTag);
        if ($checkboxOptionsField->fieldInfo !== null) {
            FormRenderer::addFieldInfoToParentHtmlTag(
                formFieldWithFieldInfo: $checkboxOptionsField,
                parentHtmlTag: $divFormCheck,
            );
        }
        FormRenderer::addErrorsToParentHtmlTag(
            formComponentWithErrors: $checkboxOptionsField,
            parentHtmlTag: $divFormCheck,
        );
        return $divFormCheck;
    }

    private function getInputTag(): HtmlTag
    {
        $inputTag = new HtmlTag(
            name: 'input',
            selfClosing: true,
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'type', text: 'checkbox'),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'name', text: $this->checkboxOptionsField->name . '[]'),
        );
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'id', text: $this->checkboxOptionsField->id),
        );
        $optionValue = $this->getOptionKey();
        $inputTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'value', text: $optionValue),
        );
        if ($this->isChecked(optionKey: $optionValue)) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromName(name: 'checked'),
            );
        }
        $ariaDescribedBy = [];
        if ($this->checkboxOptionsField->hasErrors(withChildElements: true)) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'aria-invalid', text: 'true'),
            );
            $ariaDescribedBy[] = $this->checkboxOptionsField->name . '-error';
        }
        if ($this->checkboxOptionsField->fieldInfo !== null) {
            $ariaDescribedBy[] = $this->checkboxOptionsField->name . '-info';
        }
        if (count(value: $ariaDescribedBy) > 0) {
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'aria-describedby',
                    text: implode(separator: ' ', array: $ariaDescribedBy),
                ),
            );
        }

        return $inputTag;
    }

    /**
     * The value of the checkbox: the key of a boolean field, else the first option.
     */
    private function getOptionKey(): string
    {
        if ($this->checkboxOptionsField instanceof BooleanField) {
            return BooleanField::CHECKED_KEY;
        }

        return (string) key(array: $this->checkboxOptionsField->formOptions->data);
    }

    private function isChecked(string $optionKey): bool
    {
        if ($this->checkboxOptionsField instanceof BooleanField) {
            return $this->checkboxOptionsField->isChecked();
        }

        return $this->checkboxOptionsField->isSelected(optionKey: $optionKey);
    }
}
