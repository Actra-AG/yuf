<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\template;

readonly class TemplateCacheEntry
{
    public function __construct(
        private(set) string $path,
        private(set) int $changeTime,
        private(set) int $size
    ) {
    }
}