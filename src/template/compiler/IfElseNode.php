<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\compiler;

use actra\yuf\template\parser\TagNode;
use actra\yuf\template\parser\TemplateNode;
use actra\yuf\template\parser\TextNode;

/**
 * An `if` tag with the `else` tag that follows it, found by the compiler.
 *
 * @internal
 */
final readonly class IfElseNode implements TemplateNode
{
    /**
     * @param list<TextNode> $whitespace The whitespace between `</tst:if>` and `<tst:else>`, rendered with the `if`
     *                                   branch
     */
    public function __construct(
        public TagNode $if,
        public array $whitespace,
        public ?TagNode $else,
    ) {}
}
