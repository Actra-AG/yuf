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

class FormAddRemoveTag extends TemplateTag implements TagNode
{
    public static function getName(): string
    {
        return 'formAddRemove';
    }

    public static function isElseCompatible(): bool
    {
        return false;
    }

    public static function isSelfClosing(): bool
    {
        return true;
    }

    public static function render($name, $chosenSelector, $poolSelector, TemplateEngine $tplEngine): string
    {
        $chosenEntries = $tplEngine->getDataFromSelector($chosenSelector);
        $poolEntries = [];

        if ($poolSelector !== null) {
            $poolEntries = $tplEngine->getDataFromSelector($poolSelector);
        }

        $html = '<div class="add-remove" name="' . $name . '">';

        $html .= '<ul class="option-list chosen">';

        foreach ($chosenEntries as $id => $title) {
            $html .= '<li id="' . $name . '-' . $id . '">' . $title . '</li>';
        }

        $html .= '</ul>';

        if (count($poolEntries) > 0) {
            // left or right
            $html .= '<div class="between">
				<a href="#" class="entries-add" title="add selected entries">&larr;</a>
				<br>
				<a href="#" class="entries-remove" title="remove selected entries">&rarr;</a>
			</div>';

            // Pool
            $html .= '<ul class="option-list pool">';

            foreach ($poolEntries as $id => $title) {
                $html .= '<li id="' . $name . '-' . $id . '">' . $title . '</li>';
            }

            $html .= '</ul>';
        }

        $html .= '</div>';

        return $html;
    }

    public function replaceNode(TemplateEngine $tplEngine, ElementNode $elementNode): void
    {
        $tplEngine->checkRequiredAttributes($elementNode, ['chosen', 'name']);

        $chosenEntriesSelector = $elementNode->getAttribute('chosen')->value;
        $poolEntriesSelector = $elementNode->doesAttributeExist('pool') ? $elementNode->getAttribute(
            'pool'
        )->value : null;
        $nameSelector = $elementNode->getAttribute('name')->value;

        $newNode = new TextNode();
        $newNode->content = '<?= ' . FormAddRemoveTag::class . '::render(\'' . $nameSelector . '\', \'' . $chosenEntriesSelector . '\', \'' . $poolEntriesSelector . '\', $this); ?>';

        $elementNode->parentNode->insertBefore($newNode, $elementNode);
        $elementNode->parentNode->removeNode($elementNode);
    }
}