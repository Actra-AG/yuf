<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\core\LocaleHandler;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\template\template\DirectoryTemplateCache;
use actra\yuf\template\template\TemplateEngine;
use actra\yuf\tests\Double\CoreTestInstance;
use ArrayObject;
use Override;
use ReflectionClass;

/**
 * Renders a template file with today's template engine and a template cache in a fresh temporary directory. The old
 * engine reads the snippets directory from `Core::get()` and the texts from `LocaleHandler::get()`, so the renderer
 * registers them (and removes the `LocaleHandler` again in `cleanUp()`).
 */
final readonly class OldTemplateRenderer implements TemplateRenderer
{
    private TemplateWorkDirectory $workDirectory;

    public function __construct(string $snippetsDirectory, LocaleHandler $localeHandler)
    {
        $this->workDirectory = new TemplateWorkDirectory();
        CoreTestInstance::register(
            cacheDirectory: $this->workDirectory->cacheDirectory,
            snippetsDirectory: $snippetsDirectory,
        );
        OldTemplateRenderer::resetRegisteredLocaleHandler();
        LocaleHandler::register(localeHandler: $localeHandler);
    }

    #[Override]
    public function writeTemplate(string $source): string
    {
        return $this->workDirectory->writeTemplate(source: $source);
    }

    #[Override]
    public function render(string $templateFile, ArrayObject|array|HtmlReplacementCollection $data): string
    {
        if ($data instanceof HtmlReplacementCollection) {
            $data = $data->getArrayObject();
        } elseif (is_array(value: $data)) {
            $data = new ArrayObject(array: $data);
        }
        clearstatcache();
        $outputBufferLevel = ob_get_level();

        try {
            return new TemplateEngine(
                templateCacheInterface: new DirectoryTemplateCache(
                    cachePath: $this->workDirectory->cacheDirectory,
                    templateBaseDirectory: $this->workDirectory->templateDirectory,
                ),
                tplNsPrefix: 'tst',
            )->getResultAsHtml(tplFile: $templateFile, dataPool: $data);
        } finally {
            // The old engine leaves its output buffer open when rendering throws (ob_clean() instead of ob_end_clean())
            while (ob_get_level() > $outputBufferLevel) {
                ob_end_clean();
            }
        }
    }

    #[Override]
    public function cleanUp(): void
    {
        OldTemplateRenderer::resetRegisteredLocaleHandler();
        $this->workDirectory->cleanUp();
    }

    /**
     * There is no public way to unregister the `LocaleHandler`.
     */
    private static function resetRegisteredLocaleHandler(): void
    {
        new ReflectionClass(objectOrClass: LocaleHandler::class)->setStaticPropertyValue(
            name: 'registeredInstance',
            value: null,
        );
    }
}
