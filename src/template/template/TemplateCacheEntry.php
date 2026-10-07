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
        public private(set) string $path,
        public private(set) int $changeTime,
        public private(set) int $size,
    ) {}
}
