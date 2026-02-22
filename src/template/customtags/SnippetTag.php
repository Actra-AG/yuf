<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\customtags;

use actra\yuf\Core;
use actra\yuf\template\htmlparser\ElementNode;
use actra\yuf\template\htmlparser\TextNode;
use actra\yuf\template\template\TagInline;
use actra\yuf\template\template\TagNode;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\template\template\TemplateTag;

class SnippetTag extends TemplateTag implements TagNode, TagInline
{
    public static function getName(): string
    {
        return 'snippet';
    }

    public static function isElseCompatible(): bool
    {
        return false;
    }

    public static function isSelfClosing(): bool
    {
        return true;
    }

    public static function requireFile(string $file, TemplateEngine $tplEngine): void
    {
        if ($file === '') {
            echo '';

            return;
        }
        if (!str_ends_with(
            haystack: strtolower(string: $file),
            needle: '.html'
        )) {
            echo file_get_contents(filename: $file);
            return;
        }

        echo $tplEngine->getResultAsHtml(
            tplFile: $file,
            dataPool: $tplEngine->getAllData()
        );
    }

    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $newNode = new TextNode();
        $newNode->content = $this->getReplaceValue(snippetName: $elementNode->getAttribute(name: 'name')->value);
        $elementNode->parentNode->replaceNode(
            nodeToReplace: $elementNode,
            replacementNode: $newNode
        );
    }

    private function getReplaceValue(string $snippetName): string
    {
        $snippetPath = Core::get()->siteDirectory . 'snippets' . DIRECTORY_SEPARATOR . $snippetName;

        return '<?php ' . __CLASS__ . '::requireFile(file: \'' . $snippetPath . '\', tplEngine: $this); ?>';
    }

    public function replaceInline(TemplateEngine $tplEngine, array $tagArr): string
    {
        return $this->getReplaceValue(
            snippetName: $tagArr['name']
        );
    }
}