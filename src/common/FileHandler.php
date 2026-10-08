<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use InvalidArgumentException;

/**
 * Sends one file to the browser. The path is trusted: never build it from user input without checking it against the
 * allowed directory first (see `HttpResponse::createResponseFromFilePath()`, which only resolves it).
 */
final readonly class FileHandler
{
    public function __construct(
        private string $path,
        private ?string $individualFileName = null,
        private int $maxAge = 0,
    ) {}

    /**
     * Removes the file `<directory>/<token>.<extension of $filename>`, if it exists. The token and the extension
     * are checked, so the file cannot lie outside of the directory.
     *
     * @throws InvalidArgumentException If the token contains other characters than letters, digits, ".", "_" and "-",
     *                                  or the extension other characters than letters and digits
     */
    public static function removeFile(string $directory, string $token, string $filename): void
    {
        $extension = FileHandler::getExtension(filename: $filename);
        if (preg_match(pattern: '/^[A-Za-z0-9._-]+$/D', subject: $token) !== 1) {
            throw new InvalidArgumentException(
                message: 'The token of a file to remove may only contain letters, digits, ".", "_" and "-".',
            );
        }
        if (preg_match(pattern: '/^[A-Za-z0-9]*$/D', subject: $extension) !== 1) {
            throw new InvalidArgumentException(
                message: 'The extension of a file to remove may only contain letters and digits, "' . $extension
                . '" given.',
            );
        }
        $path = rtrim(string: $directory, characters: '/\\') . DIRECTORY_SEPARATOR . $token . '.' . $extension;
        if (is_file(filename: $path)) {
            unlink(filename: $path);
        }
    }

    /**
     * @return string What follows the last ".", an empty string if the file name has no "."
     */
    public static function getExtension(string $filename): string
    {
        $position = strrpos(haystack: $filename, needle: '.');

        return $position === false ? '' : substr(string: $filename, offset: $position + 1);
    }

    public static function renderFileSize(string $filePath): string
    {
        $size = is_file(filename: $filePath) ? filesize(filename: $filePath) : false;
        if ($size === false) {
            return '0 KB';
        }

        return StringUtils::formatBytes(bytes: $size);
    }

    /**
     * Sends the file and ends the script.
     */
    public function output(HttpRequest $httpRequest, bool $forceDownload = false): void
    {
        HttpResponse::createResponseFromFilePath(
            absolutePathToFile: $this->path,
            forceDownload: $forceDownload,
            individualFileName: $this->individualFileName,
            maxAge: $this->maxAge,
            httpRequest: $httpRequest,
        )->sendAndExit();
    }
}
