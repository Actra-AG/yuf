<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\clock\FixedClock;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\LocaleHandler;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTag;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateEngine;
use DateTimeImmutable;

/**
 * Builds a template engine like `Core::createTemplateEngine()` does, with a template cache in a directory of the test
 * and a fixed clock (2026-01-02 03:04:05) instead of the system clock. `Core` itself cannot be created in tests.
 */
final class TemplateEngineFactory
{
    /**
     * @param list<TemplateTag> $ownTags Added to the built-in tags
     */
    public static function create(
        string $cacheDirectory,
        string $templateBaseDirectory,
        string $snippetsDirectory = '/nonexistent/snippets/',
        ?LocaleHandler $localeHandler = null,
        array $ownTags = [],
    ): TemplateEngine {
        $tags = TemplateTagCollection::createDefault(
            localeHandler: $localeHandler ?? new LocaleHandler(
                language: null,
                availableLanguages: new LanguageCollection(),
            ),
            snippetsDirectory: $snippetsDirectory,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
        );
        foreach ($ownTags as $tag) {
            $tags = $tags->with(tag: $tag);
        }

        return new TemplateEngine(
            cache: new DirectoryTemplateCache(
                cacheDirectory: $cacheDirectory,
                templateBaseDirectory: $templateBaseDirectory,
            ),
            tags: $tags,
        );
    }
}
