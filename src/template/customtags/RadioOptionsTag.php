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
use Override;

class RadioOptionsTag extends TemplateTag implements TagNode
{
    #[Override]
    public static function getName(): string
    {
        return 'radioOptions';
    }

    #[Override]
    public static function isElseCompatible(): bool
    {
        return false;
    }

    #[Override]
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
            multiple: false,
        );
    }

    #[Override]
    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        CustomTagsHelper::replaceOptionsNode(templateEngine: $tplEngine, elementNode: $elementNode, multiple: false);
    }
}
