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
use Exception;

class ElseTag extends TemplateTag implements TagNode
{
    public static function getName(): string
    {
        return 'else';
    }

    public static function isElseCompatible(): bool
    {
        return false;
    }

    public static function isSelfClosing(): bool
    {
        return false;
    }

    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $lastTplTag = $tplEngine->lastTplTag;

        if ($lastTplTag === null) {
            throw new Exception('There is no custom tag that can be followed by an ElseTag');
        }

        $phpCode = '<?php } else { ?>';
        $phpCode .= $elementNode->getInnerHtml();
        $phpCode .= '<?php } ?>';

        $textNode = new TextNode();
        $textNode->content = $phpCode;

        $elementNode->parentNode->replaceNode($elementNode, $textNode);

        $elementNode->parentNode->removeNode($elementNode);
    }
}