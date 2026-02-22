<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\customtags;

use actra\yuf\html\HtmlTagAttribute;
use actra\yuf\template\htmlparser\ElementNode;
use actra\yuf\template\template\TagNode;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\template\template\TemplateTag;

class OptionTag extends TemplateTag implements TagNode
{
    public static function getName(): string
    {
        return 'option';
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
        $sels = $elementNode->getAttribute('selection')->value;
        $valueAttr = $elementNode->getAttribute('value')->value;
        $value = is_numeric($valueAttr) ? $valueAttr : "'" . $valueAttr . "'";
        $type = $elementNode->getAttribute('type')->value;
        $elementNode->removeAttribute('selection');

        $elementNode->namespace = null;
        $elementNode->tagName = 'input';
        if ($sels !== null) {
            $elementNode->tagExtension = ' <?php echo in_array(' . $value . ', $this->getData(\'' . $sels . '\'))?\' checked="checked"\':null; ?>';
        }
        $elementNode->addAttribute(new HtmlTagAttribute('type', $type, true));
    }
}