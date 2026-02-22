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

class FormComponentTag extends TemplateTag implements TagNode
{
    public static function getName(): string
    {
        return 'formComponent';
    }

    public static function isElseCompatible(): bool
    {
        return false;
    }

    public static function isSelfClosing(): bool
    {
        return true;
    }

    public static function render($formSelector, $componentName, TemplateEngine $tplEngine): string
    {
        $callback = [$tplEngine->getDataFromSelector($formSelector), 'getChildComponent'];
        $component = call_user_func($callback, $componentName);

        return call_user_func([$component, 'render']);
    }

    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $tplEngine->checkRequiredAttributes($elementNode, ['form', 'name']);

        // DATA
        $newNode = new TextNode();
        $newNode->content = '<?= ' . FormComponentTag::class . '::render(\'' . $elementNode->getAttribute(
                'form'
            )->value . '\', \'' . $elementNode->getAttribute('name')->value . '\', $this); ?>';

        $elementNode->parentNode->insertBefore($newNode, $elementNode);
        $elementNode->parentNode->removeNode($elementNode);
    }
}