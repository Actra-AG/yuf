<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\template\tag\TemplateTag;
use ArrayObject;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Base class of the tests of the new template engine: renders template sources with the built-in tags, a fixed clock
 * (2026-01-02 03:04:05), the snippets of tests/Fixture/template/snippets/ and the texts of
 * tests/Fixture/template/lang.lang.php.
 */
abstract class NewEngineTestCase extends TestCase
{
    private TemplateRenderer $renderer;

    #[Override]
    protected function setUp(): void
    {
        $this->renderer = $this->createRenderer(ownTags: []);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->renderer->cleanUp();
    }

    protected static function fixtureDirectory(): string
    {
        return dirname(path: __DIR__, levels: 2) . '/Fixture/template/';
    }

    /**
     * Replaces the renderer by one that also knows the own tags.
     *
     * @param list<TemplateTag> $ownTags
     */
    protected function useOwnTags(array $ownTags): void
    {
        $this->renderer->cleanUp();
        $this->renderer = $this->createRenderer(ownTags: $ownTags);
    }

    protected function writeTemplate(string $source): string
    {
        return $this->renderer->writeTemplate(source: $source);
    }

    /**
     * @param ArrayObject<string, mixed>|array<string, mixed>|HtmlReplacementCollection $data
     */
    protected function render(string $source, ArrayObject|array|HtmlReplacementCollection $data = []): string
    {
        return $this->renderer->render(templateFile: $this->writeTemplate(source: $source), data: $data);
    }

    /**
     * @param ArrayObject<string, mixed>|array<string, mixed>|HtmlReplacementCollection $data
     */
    protected function renderFile(string $templateFile, ArrayObject|array|HtmlReplacementCollection $data = []): string
    {
        return $this->renderer->render(templateFile: $templateFile, data: $data);
    }

    /**
     * @param list<TemplateTag> $ownTags
     */
    private function createRenderer(array $ownTags): TemplateRenderer
    {
        $localeHandler = new LocaleHandler(language: null, availableLanguages: new LanguageCollection());
        $localeHandler->loadLanguageFile(filePath: NewEngineTestCase::fixtureDirectory() . 'lang.lang.php');

        return new NewTemplateRenderer(
            snippetsDirectory: NewEngineTestCase::fixtureDirectory() . 'snippets/',
            localeHandler: $localeHandler,
            ownTags: $ownTags,
        );
    }
}
