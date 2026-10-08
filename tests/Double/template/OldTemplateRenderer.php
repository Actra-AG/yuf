<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use actra\yuf\template\template\DirectoryTemplateCache;
use actra\yuf\template\template\TemplateEngine;
use ArrayObject;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Renders a template file with today's template engine and a template cache in a fresh temporary directory. The
 * characterization tests only use this class, so a renderer for the new engine can replace it.
 */
final class OldTemplateRenderer
{
    private readonly string $workDirectory;
    private readonly string $templateDirectory;
    private readonly string $cacheDirectory;
    private int $templateCounter = 0;

    public function __construct()
    {
        $this->workDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-template-test-' . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        $this->templateDirectory = $this->workDirectory . 'templates' . DIRECTORY_SEPARATOR;
        $this->cacheDirectory = $this->workDirectory . 'cache' . DIRECTORY_SEPARATOR;
        mkdir(directory: $this->templateDirectory, recursive: true);
        mkdir(directory: $this->cacheDirectory, recursive: true);
    }

    public function getCacheDirectory(): string
    {
        return $this->cacheDirectory;
    }

    /**
     * Writes the template source to a file in the temporary template directory and returns its path.
     */
    public function writeTemplate(string $source): string
    {
        $templateFile = $this->templateDirectory . 'template' . ++$this->templateCounter . '.html';
        file_put_contents(filename: $templateFile, data: $source);

        return $templateFile;
    }

    /**
     * @param ArrayObject<string, mixed> $data
     */
    public function render(string $templateFile, ArrayObject $data): string
    {
        clearstatcache();
        $outputBufferLevel = ob_get_level();

        try {
            return new TemplateEngine(
                templateCacheInterface: new DirectoryTemplateCache(
                    cachePath: $this->cacheDirectory,
                    templateBaseDirectory: $this->templateDirectory,
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

    public function cleanUp(): void
    {
        if (!is_dir(filename: $this->workDirectory)) {
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            iterator: new RecursiveDirectoryIterator(
                directory: $this->workDirectory,
                flags: FilesystemIterator::SKIP_DOTS,
            ),
            mode: RecursiveIteratorIterator::CHILD_FIRST,
        );
        /** @var SplFileInfo $entry */
        foreach ($iterator as $entry) {
            if ($entry->isDir()) {
                rmdir(directory: $entry->getPathname());
            } else {
                unlink(filename: $entry->getPathname());
            }
        }
        rmdir(directory: $this->workDirectory);
    }
}
