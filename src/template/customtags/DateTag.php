<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\customtags;

use actra\yuf\template\htmlparser\ElementNode;
use actra\yuf\template\htmlparser\TextNode;
use actra\yuf\template\template\TagNode;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\template\template\TemplateTag;
use Override;

class DateTag extends TemplateTag implements TagNode
{
    #[Override]
    public static function getName(): string
    {
        return 'date';
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
        $format = $elementNode->getAttribute('format')->value;
        $replNode = new TextNode();
        $replNode->content = '<?php echo date(\'' . $format . '\'); ?>';

        $elementNode->parentNode->replaceNode($elementNode, $replNode);
    }
}
