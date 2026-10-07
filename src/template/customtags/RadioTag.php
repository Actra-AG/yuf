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

class RadioTag extends TemplateTag implements TagNode
{
    #[Override]
    public static function getName(): string
    {
        return 'radio';
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

    #[Override]
    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        CustomTagsHelper::replaceRadioOrCheckboxFieldNode(
            elementNode: $elementNode,
            isRadio: true,
        );
    }
}
