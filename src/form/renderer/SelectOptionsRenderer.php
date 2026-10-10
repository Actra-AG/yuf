<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\renderer;

use actra\yuf\form\component\field\MultiSelectOptionsField;
use actra\yuf\form\component\field\SelectOptionsField;
use actra\yuf\form\FormOption;
use actra\yuf\form\FormRenderer;
use actra\yuf\html\HtmlTag;
use actra\yuf\html\HtmlTagAttribute;
use Override;

final class SelectOptionsRenderer extends FormRenderer
{
    public function __construct(private readonly SelectOptionsField|MultiSelectOptionsField $selectOptionsField) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $selectOptionsField = $this->selectOptionsField;
        $isMultiple = $selectOptionsField->isMultiple();
        $fieldName = $selectOptionsField->name;
        $selectTag = new HtmlTag(name: 'select', selfClosing: false);
        $selectTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(
                name: 'name',
                text: $isMultiple ? $fieldName . '[]' : $fieldName,
            ),
        );
        foreach ($selectOptionsField->getDataAttributes() as $key => $val) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: $val === ''
                    ? HtmlTagAttribute::fromName(name: 'data-' . $key)
                    : HtmlTagAttribute::fromText(name: 'data-' . $key, text: $val),
            );
        }
        $selectTag->addHtmlTagAttribute(
            htmlTagAttribute: HtmlTagAttribute::fromText(name: 'id', text: $selectOptionsField->id),
        );
        if (count(value: $selectOptionsField->cssClasses) > 0) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'class',
                    text: implode(separator: ' ', array: $selectOptionsField->cssClasses),
                ),
            );
        }
        if ($isMultiple) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromName(name: 'multiple'),
            );
        }
        if ($selectOptionsField->placeholder !== null) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'placeholder',
                    text: $selectOptionsField->placeholder,
                ),
            );
        }
        if ($selectOptionsField->autoComplete !== null) {
            $selectTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'autocomplete',
                    text: $selectOptionsField->autoComplete->value,
                ),
            );
        }
        $options = $selectOptionsField->formOptions->getItems();
        if (
            $selectOptionsField->renderEmptyValueOption
            && !$selectOptionsField->formOptions->exists(key: '')
        ) {
            array_unshift($options, new FormOption(key: '', htmlText: $selectOptionsField->emptyValueLabel));
        }
        foreach ($options as $option) {
            $optionTag = new HtmlTag(
                name: 'option',
                selfClosing: false,
            );
            $optionTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'value', text: $option->key),
            );
            if ($selectOptionsField->isSelected(optionKey: $option->key)) {
                $optionTag->addHtmlTagAttribute(
                    htmlTagAttribute: HtmlTagAttribute::fromName(name: 'selected'),
                );
            }
            $optionTag->addText(htmlText: $option->htmlText);
            $selectTag->addTag(htmlTag: $optionTag);
        }
        return $selectTag;
    }
}
