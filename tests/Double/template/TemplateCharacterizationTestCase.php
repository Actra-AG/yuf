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
use actra\yuf\template\TemplateException;
use ArrayObject;
use Override;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Base class of the template characterization tests: the only place that knows which engine renders the templates.
 * Every test class exists once for the old and once for the new engine (`isNewEngine()`). A test that pins behaviour
 * that the new engine changes on purpose (design.md) asks `isNewEngine()` and states both results.
 */
abstract class TemplateCharacterizationTestCase extends TestCase
{
    private TemplateRenderer $renderer;

    abstract protected function isNewEngine(): bool;

    #[Override]
    protected function setUp(): void
    {
        $localeHandler = new LocaleHandler(language: null, availableLanguages: new LanguageCollection());
        $localeHandler->loadLanguageFile(filePath: TemplateCharacterizationTestCase::fixtureDirectory() . 'lang.lang.php');
        $snippetsDirectory = TemplateCharacterizationTestCase::fixtureDirectory() . 'snippets/';
        $this->renderer = $this->isNewEngine()
            ? new NewTemplateRenderer(snippetsDirectory: $snippetsDirectory, localeHandler: $localeHandler)
            : new OldTemplateRenderer(snippetsDirectory: $snippetsDirectory, localeHandler: $localeHandler);
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
     * The expected result of the engine under test, for a case where the new engine differs on purpose.
     */
    protected function forEngine(string $old, string $new): string
    {
        return $this->isNewEngine() ? $new : $old;
    }

    /**
     * Expects the exception of the engine under test: the new engine throws a `TemplateException`.
     *
     * @param class-string<Throwable> $oldClass
     */
    protected function expectEngineException(string $oldClass, string $oldMessage, string $newMessage, int $oldCode = 0): void
    {
        if ($this->isNewEngine()) {
            $this->expectException(TemplateException::class);
            $this->expectExceptionMessageIs($newMessage);

            return;
        }
        $this->expectException($oldClass);
        $this->expectExceptionCode($oldCode);
        $this->expectExceptionMessageIs($oldMessage);
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
        return $this->renderer->render(templateFile: $templateFile, data: $data);
    }
}
