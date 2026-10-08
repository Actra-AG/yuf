<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\parser;

/**
 * An element tag that is opened and not yet closed while the parser builds the tree.
 *
 * @internal
 */
final class OpenTagFrame
{
    /** @var list<TemplateNode> */
    public array $children = [];

    /**
     * @param array<string, string> $attributes
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes,
        public readonly int $line,
    ) {}
}
