<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\customtags;

use actra\yuf\core\LocaleHandler;
use actra\yuf\template\htmlparser\ElementNode;
use actra\yuf\template\htmlparser\TextNode;
use actra\yuf\template\template\TagInline;
use actra\yuf\template\template\TagNode;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\template\template\TemplateTag;
use Override;

class LangTag extends TemplateTag implements TagNode, TagInline
{
    #[Override]
    public static function getName(): string
    {
        return 'lang';
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

    public static function getText($key, array $phpVars): string
    {
        return LocaleHandler::get()->getText(key: $key, replacements: $phpVars);
    }

    #[Override]
    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $replValue = LangTag::replace(
            $elementNode->getAttribute('key')->value,
            $elementNode->getAttribute('vars')->value,
        );

        $replNode = new TextNode();
        $replNode->content = $replValue;

        $elementNode->parentNode->replaceNode($elementNode, $replNode);
    }

    public function replace($key, ?string $vars = null): string
    {
        $phpVars = ', array()';
        if ($vars !== null) {
            $varsEx = explode(',', $vars);
            $varsFull = [];

            foreach ($varsEx as $v) {
                $varsFull[] = '\'' . $v . '\' => LangTag::getData(\'' . $v . '\')';
            }

            $phpVars = ',array(' . implode(separator: ', ', array: $varsFull) . ')';
        }

        return '<?php echo ' . __CLASS__ . '::getText(\'' . $key . '\'' . $phpVars . '); ?>';
    }

    #[Override]
    public function replaceInline(TemplateEngine $tplEngine, $tagArr): string
    {
        $vars = (array_key_exists('vars', $tagArr)) ? $tagArr['vars'] : null;

        return LangTag::replace($tagArr['key'], $vars);
    }
}
