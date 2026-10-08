<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\cache;

/**
 * Keeps the compiled PHP code of templates. Implement it to store the compiled code somewhere else than in a
 * directory.
 */
interface TemplateCache
{
    /**
     * Returns the path of the compiled PHP file of the template, or `null` if there is none or if it is older than the
     * template.
     */
    public function find(string $templateFile): ?string;

    /**
     * Stores the compiled PHP code of the template and returns the path of the compiled PHP file.
     */
    public function store(string $templateFile, string $compiledCode): string;
}
