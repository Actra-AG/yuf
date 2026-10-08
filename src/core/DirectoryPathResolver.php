<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * Resolves the directory settings of `Core`: replaces the placeholders `{DOCUMENT_ROOT}`, `{BASE_DIRECTORY}` and
 * `{APP_DIRECTORY}`, removes double separators, `.` and `..` and returns an absolute path with a trailing separator.
 * Pure string logic; `Core` creates the directories.
 *
 * @internal
 */
final readonly class DirectoryPathResolver
{
    public static function resolve(
        string $path,
        string $documentRoot,
        string $baseDirectory,
        string $appDirectory,
    ): string {
        $path = str_replace(
            search: [
                '{DOCUMENT_ROOT}',
                '{BASE_DIRECTORY}',
                '{APP_DIRECTORY}',
                DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR,
            ],
            replace: [
                $documentRoot,
                $baseDirectory,
                $appDirectory,
                DIRECTORY_SEPARATOR,
            ],
            subject: $path,
        );
        $path = DirectoryPathResolver::normalize(path: $path);

        return str_ends_with(haystack: $path, needle: DIRECTORY_SEPARATOR) ? $path : $path . DIRECTORY_SEPARATOR;
    }

    private static function normalize(string $path): string
    {
        $safe = [];
        foreach (explode(separator: '/', string: $path) as $part) {
            if ($part === '.' || $part === '') {
                continue;
            }
            if ($part === '..') {
                array_pop(array: $safe);
            } else {
                $safe[] = $part;
            }
        }

        return '/' . implode(separator: '/', array: $safe);
    }
}
