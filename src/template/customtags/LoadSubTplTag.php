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
use Override;

class LoadSubTplTag extends TemplateTag implements TagNode
{
    #[Override]
    public static function getName(): string
    {
        return 'loadSubTpl';
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

    /**
     * A special method that belongs to the LoadSubTplTag class but needs none static properties from this class and is called from the cached template files.
     *
     * @param string $file The full filepath to include (OR magic {this})
     */
    public static function requireFile(string $file, TemplateEngine $tplEngine): void
    {
        if ($file === '') {
            echo '';

            return;
        }
        echo $tplEngine->getResultAsHtml(tplFile: $file, dataPool: $tplEngine->getAllData());
    }

    #[Override]
    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $dataKey = $elementNode->getAttribute(name: 'tplfile')->value;
        $tplFile = (preg_match(
            pattern: '/^{(.+)}$/',
            subject: $dataKey,
            matches: $res,
        ) === 1) ? '$this->getData(\'' . $res[1] . '\')' : '\'' . $dataKey . '\'';

        $newNode = new TextNode();
        $newNode->content = '<?php ' . __NAMESPACE__ . '\\LoadSubTplTag::requireFile(' . $tplFile . ', $this); ?>';

        $elementNode->parentNode->replaceNode(nodeToReplace: $elementNode, replacementNode: $newNode);
    }

    public function replaceInline(): void
    {
        throw new Exception(message: 'Don\'t use this tag (LoadSubTpl) inline!');
    }
}
