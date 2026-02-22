<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\customtags;

use actra\yuf\template\htmlparser\ElementNode;
use actra\yuf\template\htmlparser\TextNode;
use actra\yuf\template\template\TagInline;
use actra\yuf\template\template\TagNode;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\template\template\TemplateTag;

class TextTag extends TemplateTag implements TagNode, TagInline
{
    public static function getName(): string
    {
        return 'text';
    }

    public static function isElseCompatible(): bool
    {
        return false;
    }

    public static function isSelfClosing(): bool
    {
        return true;
    }

    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $replValue = $this->replace($elementNode->getAttribute('value')->value);

        $replNode = new TextNode();
        $replNode->content = $replValue;

        $elementNode->parentNode->replaceNode($elementNode, $replNode);
    }

    public function replace($params): string
    {
        return '<?php echo $this->getDataFromSelector(\'' . $params . '\'); ?>';
    }

    public function replaceInline(TemplateEngine $tplEngine, $tagArr): string
    {
        return $this->replace($tagArr['value']);
    }
}