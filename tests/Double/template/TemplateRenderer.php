<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\core\LocaleHandler;
use actra\yuf\html\HtmlReplacementCollection;
use actra\yuf\template\tag\TemplateTag;
use actra\yuf\template\TemplateData;
use actra\yuf\template\TemplateEngine;
use ArrayObject;

/**
 * Renders template files with the template engine and a template cache in a fresh temporary directory. The clock is
 * fixed at 2026-01-02 03:04:05.
 */
final readonly class TemplateRenderer
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
        $this->engine = TemplateEngineFactory::create(
            cacheDirectory: $this->workDirectory->cacheDirectory,
            templateBaseDirectory: $this->workDirectory->templateDirectory,
            snippetsDirectory: $snippetsDirectory,
            localeHandler: $localeHandler,
            ownTags: $ownTags,
        );
    }

    /**
     * Writes the template source to a file in a temporary template directory and returns its path.
     */
    public function writeTemplate(string $source): string
    {
        return $this->workDirectory->writeTemplate(source: $source);
    }

    /**
     * @param ArrayObject<string, mixed>|array<string, mixed>|HtmlReplacementCollection $data
     */
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

    public function cleanUp(): void
    {
        $this->workDirectory->cleanUp();
    }
}
