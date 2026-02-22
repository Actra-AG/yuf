<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\template;

use actra\yuf\template\htmlparser\ElementNode;
use Exception;

abstract class TemplateTag
{
    public function __construct()
    {
        if (($this instanceof TagNode) === false && ($this instanceof TagInline) === false) {
            throw new Exception(
                'The class "' . get_class(
                    $this
                ) . '" does not implement the class "TagNode" or "TagInline" and is so recognized as an illegal class for a custom tag."'
            );
        }
    }

    abstract public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void;
}