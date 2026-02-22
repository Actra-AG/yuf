<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\template;

interface TagNode
{
    public static function getName(): string;

    public static function isElseCompatible(): bool;

    public static function isSelfClosing(): bool;
}