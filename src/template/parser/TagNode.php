<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\parser;

/**
 * A tag of the template engine: an inline tag `{tst:name attr='value'}`, an element tag `<tst:name attr="value"/>` or
 * an element tag with children `<tst:name>…</tst:name>`.
 *
 * @internal
 */
final readonly class TagNode implements TemplateNode
{
    /**
     * @param array<string, string> $attributes
     * @param list<TemplateNode> $children
     * @param bool $hasBody true for `<tst:name>…</tst:name>` (also when empty), false for inline tags and `<tst:name/>`
     */
    public function __construct(
        public string $name,
        public array $attributes,
        public array $children,
        public int $line,
        public bool $hasBody,
    ) {}
}
