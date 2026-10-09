<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\template\cache\TemplateCache;
use Override;

/**
 * Counts the lookups of a template cache it wraps.
 */
final class CountingTemplateCache implements TemplateCache
{
    public private(set) int $finds = 0;

    public function __construct(private readonly TemplateCache $cache) {}

    #[Override]
    public function find(string $templateFile): ?string
    {
        $this->finds++;

        return $this->cache->find(templateFile: $templateFile);
    }

    #[Override]
    public function store(string $templateFile, string $compiledCode): string
    {
        return $this->cache->store(templateFile: $templateFile, compiledCode: $compiledCode);
    }
}
