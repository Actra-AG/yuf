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
use LogicException;
use Override;

abstract class DefaultOptionsRenderer extends FormRenderer
{
    protected function __construct(
        private readonly OptionsField $optionsField,
        private readonly string       $inputFieldType,
        private readonly bool         $acceptMultipleValues,
    ) {}

    #[Override]
    public function createHtmlTag(): HtmlTag
    {
        $optionsField = $this->optionsField;
        $options = $optionsField->formOptions->data;
        if (count(value: $options) === 0) {
            throw new LogicException(message: 'There must be at least one option!');
        }
        $ulTag = DefaultOptionsRenderer::createUlTag(optionsField: $optionsField);
        foreach ($options as $key => $htmlText) {
            $liTag = new HtmlTag(
                name: 'li',
                selfClosing: false,
            );
            $ulTag->addTag(htmlTag: $liTag);
            $liTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'class', text: 'form-check'),
            );
            $inputTag = new HtmlTag(
                name: 'input',
                selfClosing: true,
            );
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'type', text: $this->inputFieldType),
            );
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(
                    name: 'name',
                    text: ($this->acceptMultipleValues) ? $optionsField->name . '[]' : $optionsField->name,
                ),
            );
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'id', text: $optionsField->id . '_' . $key),
            );
            $inputTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'value', text: (string) $key),
            );
            if ($optionsField->isSelected(optionKey: (string) $key)) {
                $inputTag->addHtmlTagAttribute(
                    htmlTagAttribute: HtmlTagAttribute::fromName(name: 'checked'),
                );
            }
            $liTag->addTag(htmlTag: $inputTag);
            $labelTag = new HtmlTag(
                name: 'label',
                selfClosing: false,
            );
            $labelTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'class', text: 'form-check-label'),
            );
            $labelTag->addHtmlTagAttribute(
                htmlTagAttribute: HtmlTagAttribute::fromText(name: 'for', text: $optionsField->id . '_' . $key),
            );
            $labelTag->addText(htmlText: $htmlText);
            $liTag->addTag(htmlTag: $labelTag);
        }

        return $ulTag;
    }

    public static function createUlTag(OptionsField $optionsField): HtmlTag
    {
        $listTagClasses = $optionsField->getListTagClasses();
        if ($optionsField->hasErrors(withChildElements: true)) {
            $listTagClasses[] = 'list-has-error';
        }
        $htmlTagAttributes = [];
        if (count(value: $listTagClasses) > 0) {
            $htmlTagAttributes[] = HtmlTagAttribute::fromText(
                name: 'class',
                text: implode(separator: ' ', array: $listTagClasses),
            );
        }

        return new HtmlTag(
            name: 'ul',
            selfClosing: false,
            htmlTagAttributes: $htmlTagAttributes,
        );
    }
}
