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
use LogicException;

abstract class FormRenderer
{
    private ?HtmlTag $htmlTag = null; // The base Tag-Element for this renderer, which may contain child-elements

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

    /** The descending classes must use this method to prepare the base Tag-Element */
    abstract public function prepare(): void;

    /**
     * Prepares the renderer and returns its base Tag-Element. Use this to render a component through its renderer.
     *
     * @throws LogicException If `prepare()` did not set a base Tag-Element
     */
    final public function prepareHtmlTag(): HtmlTag
    {
        $this->prepare();

        return $this->htmlTag ?? throw new LogicException(
            message: static::class . '::prepare() must set the base Tag-Element with setHtmlTag().',
        );
    }

    /**
     * Get the current base Tag-Element for this renderer
     *
     * @return HtmlTag|null The current base Tag-Element or null, if not set
     */
    public function getHtmlTag(): ?HtmlTag
    {
        return $this->htmlTag;
    }

    /**
     * Method to set the base Tag-Element. It's not allowed to overwrite it, if already set!
     *
     * @param HtmlTag $htmlTag The Tag-Element to be set
     */
    protected function setHtmlTag(HtmlTag $htmlTag): void
    {
        if ($this->htmlTag !== null) {
            throw new LogicException(message: 'You cannot overwrite an already defined Tag-Element.');
        }
        $this->htmlTag = $htmlTag;
    }
}
