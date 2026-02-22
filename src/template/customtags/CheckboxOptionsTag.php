<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\customtags;

use actra\yuf\template\htmlparser\ElementNode;
use actra\yuf\template\template\TagNode;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\template\template\TemplateTag;

class CheckboxOptionsTag extends TemplateTag implements TagNode
{
    public static function getName(): string
    {
        return 'checkboxOptions';
    }

    public static function isElseCompatible(): bool
    {
        return false;
    }

    public static function isSelfClosing(): bool
    {
        return true;
    }

    public static function render(TemplateEngine $tplEngine, $fldName, $optionsSelector, $checkedSelector): string
    {
        return CustomTagsHelper::renderOptionsTag(
            templateEngine: $tplEngine,
            fieldName: $fldName,
            optionsSelector: $optionsSelector,
            checkedSelector: $checkedSelector,
            multiple: true
        );
    }

    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        CustomTagsHelper::replaceOptionsNode(templateEngine: $tplEngine, elementNode: $elementNode, multiple: true);
    }
}