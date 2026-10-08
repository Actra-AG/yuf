<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\template;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * A fresh temporary directory with a template directory and a cache directory for one test, removed by `cleanUp()`.
 */
final class TemplateWorkDirectory
{
    public readonly string $templateDirectory;
    public readonly string $cacheDirectory;
    private readonly string $workDirectory;
    private int $templateCounter = 0;

    public function __construct()
    {
        $this->workDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-template-test-' . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        $this->templateDirectory = $this->workDirectory . 'templates' . DIRECTORY_SEPARATOR;
        $this->cacheDirectory = $this->workDirectory . 'cache' . DIRECTORY_SEPARATOR;
        mkdir(directory: $this->templateDirectory, recursive: true);
        mkdir(directory: $this->cacheDirectory, recursive: true);
    }

    /**
     * Writes the template source to a file in the template directory and returns its path.
     */
    public function writeTemplate(string $source): string
    {
        $templateFile = $this->templateDirectory . 'template' . ++$this->templateCounter . '.html';
        file_put_contents(filename: $templateFile, data: $source);

        return $templateFile;
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
