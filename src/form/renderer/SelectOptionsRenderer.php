<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

class SelectOptionsRenderer extends FormRenderer
{
    public function __construct(private readonly SelectOptionsField|MultiSelectOptionsField $selectOptionsField) {}

    #[Override]
    public function prepare(): void
    {
        $selectOptionsField = $this->selectOptionsField;
        $isMultiple = $selectOptionsField->isMultiple();
        $fieldName = $selectOptionsField->name;
        $selectTag = new HtmlTag(name: 'select', selfClosing: false);
        $selectTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'name',
                value: $isMultiple ? $fieldName . '[]' : $fieldName,
                valueIsEncodedForRendering: true,
            ),
        );
        foreach ($selectOptionsField->getDataAttributes() as $key => $val) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'data-' . $key,
                    value: $val === '' ? null : $val,
                    valueIsEncodedForRendering: true,
                ),
            );
        }
        $selectTag->addHtmlTagAttribute(
            htmlTagAttribute: new HtmlTagAttribute(
                name: 'id',
                value: $selectOptionsField->id,
                valueIsEncodedForRendering: true,
            ),
        );
        if (count(value: $selectOptionsField->cssClasses) > 0) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'class',
                    value: implode(separator: ' ', array: $selectOptionsField->cssClasses),
                    valueIsEncodedForRendering: true,
                ),
            );
        }
        if ($isMultiple) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'multiple',
                    value: null,
                    valueIsEncodedForRendering: true,
                ),
            );
        }
        if ($selectOptionsField->placeholder !== null) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'placeholder',
                    value: $selectOptionsField->placeholder,
                    valueIsEncodedForRendering: true,
                ),
            );
        }
        if ($selectOptionsField->autoComplete !== null) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'autocomplete',
                    value: $selectOptionsField->autoComplete->value,
                    valueIsEncodedForRendering: true,
                ),
            );
        }
        $options = $selectOptionsField->formOptions->data;
        if (
            $selectOptionsField->renderEmptyValueOption
            && !array_key_exists(key: '', array: $options)
        ) {
            $options = ['' => $selectOptionsField->emptyValueLabel] + $options;
        }
        foreach ($options as $key => $htmlText) {
            $optionTag = new HtmlTag(
                name: 'option',
                selfClosing: false,
            );
            $optionTag->addHtmlTagAttribute(
                htmlTagAttribute: new HtmlTagAttribute(
                    name: 'value',
                    value: (string) $key,
                    valueIsEncodedForRendering: true,
                ),
            );
            if ($selectOptionsField->isSelected(optionKey: (string) $key)) {
                $optionTag->addHtmlTagAttribute(
                    htmlTagAttribute: new HtmlTagAttribute(
                        name: 'selected',
                        value: null,
                        valueIsEncodedForRendering: true,
                    ),
                );
            }
            $optionTag->addText(htmlText: $htmlText);
            $selectTag->addTag(htmlTag: $optionTag);
        }
        $this->setHtmlTag(htmlTag: $selectTag);
    }
}
