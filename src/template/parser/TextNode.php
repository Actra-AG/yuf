<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\parser;

/**
 * Template text that is output unchanged.
 *
 * @internal
 */
final readonly class TextNode implements TemplateNode
{
    public function __construct(public string $text) {}
}
