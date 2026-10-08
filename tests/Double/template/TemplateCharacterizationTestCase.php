<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\tests\Double\CoreTestInstance;
use ArrayObject;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Base class of the template characterization tests: the only place that knows which engine renders the templates.
 */
abstract class TemplateCharacterizationTestCase extends TestCase
{
    private OldTemplateRenderer $renderer;

    #[Override]
    protected function setUp(): void
    {
        $this->renderer = new OldTemplateRenderer();
        CoreTestInstance::register(
            cacheDirectory: $this->renderer->getCacheDirectory(),
            snippetsDirectory: TemplateCharacterizationTestCase::fixtureDirectory() . 'snippets/',
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->renderer->cleanUp();
    }

    protected static function fixtureDirectory(): string
    {
        // Normalized (no "..") so the template cache can create its directories
        return dirname(path: __DIR__, levels: 2) . '/Fixture/template/';
    }

    protected static function projectDirectory(): string
    {
        return dirname(path: __DIR__, levels: 3) . '/';
    }

    protected function writeTemplate(string $source): string
    {
        return $this->renderer->writeTemplate(source: $source);
    }

    /**
     * @param ArrayObject<string, mixed>|array<string, mixed>|HtmlReplacementCollection $data
     */
    protected function renderSource(string $source, ArrayObject|array|HtmlReplacementCollection $data = []): string
    {
        return $this->renderFile(
            templateFile: $this->writeTemplate(source: $source),
            data: $data,
        );
    }

    /**
     * @param ArrayObject<string, mixed>|array<string, mixed>|HtmlReplacementCollection $data
     */
    protected function renderFile(string $templateFile, ArrayObject|array|HtmlReplacementCollection $data = []): string
    {
        if ($data instanceof HtmlReplacementCollection) {
            $data = $data->getArrayObject();
        } elseif (is_array(value: $data)) {
            $data = new ArrayObject(array: $data);
        }

        return $this->renderer->render(templateFile: $templateFile, data: $data);
    }
}
