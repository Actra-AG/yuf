<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\customtags;

use actra\yuf\template\template\TagInline;
use DateTime;
use actra\yuf\template\htmlparser\ElementNode;
use actra\yuf\template\htmlparser\TextNode;
use actra\yuf\template\template\TagNode;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\template\template\TemplateTag;

class PrintTag extends TemplateTag implements TagNode, TagInline
{
    public static function getName(): string
    {
        return 'print';
    }

    public static function isElseCompatible(): bool
    {
        return false;
    }

    public static function isSelfClosing(): bool
    {
        return true;
    }

    public static function generateOutput(TemplateEngine $templateEngine, $selector): float|bool|int|string
    {
        $data = $templateEngine->getDataFromSelector($selector);

        if ($data instanceof DateTime) {
            return $data->format('Y-m-d H:i:s');
        } elseif (is_scalar($data) === false) {
            return print_r($data, true);
        }

        return $data;
    }

    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $replValue = $this->replace($elementNode->getAttribute('var')->value);

        $replNode = new TextNode();
        $replNode->content = $replValue;

        $elementNode->parentNode->replaceNode($elementNode, $replNode);
    }

    public function replace($selector): string
    {
        return '<?php echo ' . __CLASS__ . '::generateOutput($this, \'' . $selector . '\'); ?>';
    }

    public function replaceInline(TemplateEngine $tplEngine, $tagArr): string
    {
        return $this->replace($tagArr['var']);
    }
}