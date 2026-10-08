<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\html\HtmlReplacementCollection;
use ArrayObject;

/**
 * Renders template files with one of the two template engines, so the characterization tests run against both.
 */
interface TemplateRenderer
{
    /**
     * Writes the template source to a file in a temporary template directory and returns its path.
     */
    public function writeTemplate(string $source): string;

    /**
     * @param ArrayObject<string, mixed>|array<string, mixed>|HtmlReplacementCollection $data
     */
    public function render(string $templateFile, ArrayObject|array|HtmlReplacementCollection $data): string;

    public function cleanUp(): void;
}
