<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\template\cache;

use actra\yuf\template\compiler\TemplateCompiler;
use actra\yuf\template\TemplateException;
use Override;

/**
 * Stores the compiled templates as PHP files in a directory. The path of a compiled file contains the format version of
 * the compiler, so a yuf upgrade never runs code of an older compiler. A compiled file is outdated when the template is
 * as new or newer. The files are written atomically: temporary file in the same directory, then `rename()`.
 */
final readonly class DirectoryTemplateCache implements TemplateCache
{
    private const int DIRECTORY_MODE = 0o775;
    private const int FILE_MODE = 0o664;
    private string $templateBaseDirectory;

    /**
     * @param string $cacheDirectory Directory for the compiled files
     * @param string $templateBaseDirectory Templates below it are cached under their relative path, all others under a
     *                                      hash of their path
     */
    public function __construct(private string $cacheDirectory, string $templateBaseDirectory)
    {
        $this->templateBaseDirectory = rtrim(string: $templateBaseDirectory, characters: '/') . '/';
    }

    #[Override]
    public function find(string $templateFile): ?string
    {
        $compiledFile = $this->getCompiledFile(templateFile: $templateFile);
        if (!is_file(filename: $templateFile) || !is_file(filename: $compiledFile)) {
            return null;
        }
        $templateTime = filemtime(filename: $templateFile);
        $compiledTime = filemtime(filename: $compiledFile);
        if ($templateTime === false || $compiledTime === false || $templateTime >= $compiledTime) {
            return null;
        }

        return $compiledFile;
    }

    #[Override]
    public function store(string $templateFile, string $compiledCode): string
    {
        $compiledFile = $this->getCompiledFile(templateFile: $templateFile);
        $directory = dirname(path: $compiledFile);
        $this->createDirectory(directory: $directory);
        $temporaryFile = tempnam(directory: $directory, prefix: 'tpl');
        if ($temporaryFile === false) {
            throw new TemplateException(reason: 'Could not create a temporary file in ' . $directory);
        }
        if (file_put_contents(filename: $temporaryFile, data: $compiledCode) === false
            || !chmod(filename: $temporaryFile, permissions: DirectoryTemplateCache::FILE_MODE)
            || !rename(from: $temporaryFile, to: $compiledFile)
        ) {
            unlink(filename: $temporaryFile);

            throw new TemplateException(reason: 'Could not write the compiled template ' . $compiledFile);
        }
        $this->invalidateOpcache(compiledFile: $compiledFile);

        return $compiledFile;
    }

    /**
     * Path of the compiled file: `<cache directory>/v<format version>/<relative template path>.php`.
     */
    public function getCompiledFile(string $templateFile): string
    {
        return rtrim(string: $this->cacheDirectory, characters: '/') . '/v' . TemplateCompiler::FORMAT_VERSION . '/'
            . $this->getCacheKey(templateFile: $templateFile) . '.php';
    }

    private function createDirectory(string $directory): void
    {
        if (is_dir(filename: $directory)) {
            return;
        }
        if (
            !mkdir(directory: $directory, permissions: DirectoryTemplateCache::DIRECTORY_MODE, recursive: true)
            && !is_dir(filename: $directory)
        ) {
            throw new TemplateException(reason: 'Could not create the template cache directory ' . $directory);
        }
    }

    private function getCacheKey(string $templateFile): string
    {
        if (str_starts_with(haystack: $templateFile, needle: $this->templateBaseDirectory)) {
            $relativePath = substr(string: $templateFile, offset: strlen(string: $this->templateBaseDirectory));
            if (
                $relativePath !== ''
                && !in_array(needle: '..', haystack: explode(separator: '/', string: $relativePath), strict: true)
            ) {
                return $relativePath;
            }
        }

        return 'external/' . hash(algo: 'sha256', data: $templateFile);
    }

    private function invalidateOpcache(string $compiledFile): void
    {
        if (function_exists(function: 'opcache_invalidate')) {
            opcache_invalidate(filename: $compiledFile, force: true);
        }
    }
}
