<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\template;

interface TagInline
{
    public static function getName(): string;

    public function replaceInline(TemplateEngine $tplEngine, array $tagArr): string;
}