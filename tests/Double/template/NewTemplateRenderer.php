<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\clock\FixedClock;
use actra\yuf\core\LocaleHandler;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\template\cache\DirectoryTemplateCache;
use actra\yuf\template\tag\TemplateTag;
use actra\yuf\template\tag\TemplateTagCollection;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateEngine;
use ArrayObject;
use DateTimeImmutable;
use Override;

/**
 * Renders a template file with the new template engine and a template cache in a fresh temporary directory. The clock
 * is fixed at 2026-01-02 03:04:05.
 */
final readonly class NewTemplateRenderer implements TemplateRenderer
{
    private TemplateWorkDirectory $workDirectory;
    private TemplateEngine $engine;

    /**
     * @param list<TemplateTag> $ownTags Added to the built-in tags
     */
    public function __construct(
        string $snippetsDirectory,
        LocaleHandler $localeHandler,
        array $ownTags = [],
    ) {
        $this->workDirectory = new TemplateWorkDirectory();
        $this->engine = new TemplateEngine(
            cache: new DirectoryTemplateCache(
                cacheDirectory: $this->workDirectory->cacheDirectory,
                templateBaseDirectory: $this->workDirectory->templateDirectory,
            ),
            tags: NewTemplateRenderer::addTags(
                tags: TemplateTagCollection::createDefault(
                    localeHandler: $localeHandler,
                    snippetsDirectory: $snippetsDirectory,
                    clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
                ),
                ownTags: $ownTags,
            ),
        );
    }

    /**
     * @param list<TemplateTag> $ownTags
     */
    private static function addTags(TemplateTagCollection $tags, array $ownTags): TemplateTagCollection
    {
        foreach ($ownTags as $tag) {
            $tags = $tags->with(tag: $tag);
        }

        return $tags;
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
            $templateData = TemplateData::fromReplacements(replacements: $data);
        } else {
            $templateData = new TemplateData(values: is_array(value: $data) ? $data : $data->getArrayCopy());
        }
        clearstatcache();

        return $this->engine->render(templateFile: $templateFile, data: $templateData);
    }

    #[Override]
    public function cleanUp(): void
    {
        $this->workDirectory->cleanUp();
    }
}
