<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\upload;

use RuntimeException;

/**
 * The root directory of the uploaded files and the permissions of what lives in it: only the user of the PHP process
 * can read it (directories 0700, files 0600, whatever the umask is). A root that is a symbolic link, is no directory or
 * belongs to another user is refused with an exception, so the files are never written to a place someone else
 * controls.
 */
final readonly class UploadDirectory
{
    private const int DIRECTORY_PERMISSIONS = 0o700;
    private const int FILE_PERMISSIONS = 0o600;

    public function __construct(public string $path) {}

    /**
     * Refuses a root directory inside the document root (or equal to it), where the web server could serve the files.
     * Symbolic links in the existing part of the path are resolved; the root does not need to exist yet.
     *
     * @throws RuntimeException if the root directory lies inside the document root
     */
    public function assertOutsideOf(string $documentRoot): void
    {
        $resolvedDocumentRoot = UploadDirectory::resolve(path: $documentRoot);
        $resolvedRoot = UploadDirectory::resolve(path: $this->path);
        if (
            $resolvedRoot === $resolvedDocumentRoot
            || str_starts_with(haystack: $resolvedRoot . '/', needle: rtrim(string: $resolvedDocumentRoot, characters: '/') . '/')
        ) {
            throw new RuntimeException(
                message: 'The upload directory ' . $this->path . ' lies inside the document root ' . $documentRoot . '.',
            );
        }
    }

    /**
     * The absolute path with the symbolic links of its existing part resolved and without `.` and `..`.
     */
    private static function resolve(string $path): string
    {
        $missingParts = [];
        $existing = rtrim(string: $path, characters: '/');
        while ($existing !== '' && realpath(path: $existing) === false) {
            $missingParts[] = basename(path: $existing);
            $parent = dirname(path: $existing);
            if ($parent === $existing) {
                break;
            }
            $existing = $parent;
        }
        $resolved = realpath(path: $existing === '' ? '/' : $existing);
        $parts = $resolved === false ? [] : explode(separator: '/', string: trim(string: $resolved, characters: '/'));
        foreach (array_reverse(array: $missingParts) as $part) {
            if ($part === '..') {
                array_pop(array: $parts);
            } elseif ($part !== '.' && $part !== '') {
                $parts[] = $part;
            }
        }

        return '/' . implode(separator: '/', array: $parts);
    }

    /**
     * Whether the root directory exists; does not create it.
     *
     * @throws RuntimeException if it exists but must not be used (see the class description)
     */
    public function exists(): bool
    {
        if (!is_link(filename: $this->path) && !file_exists(filename: $this->path)) {
            return false;
        }
        $this->assertUsable();

        return true;
    }

    /**
     * Creates the root directory (and missing parent directories) with mode 0700 and makes an existing one private.
     *
     * @throws RuntimeException if the directory cannot be created or must not be used
     */
    public function ensureExists(): void
    {
        if ($this->exists()) {
            $this->makePrivate(path: $this->path);

            return;
        }
        if (!mkdir(directory: $this->path, permissions: UploadDirectory::DIRECTORY_PERMISSIONS, recursive: true)
            && !is_dir(filename: $this->path)) {
            throw new RuntimeException(message: 'The upload directory ' . $this->path . ' cannot be created.');
        }
        // mkdir() applies the umask, so the permissions are set explicitly
        $this->assertUsable();
        $this->makePrivate(path: $this->path);
    }

    /**
     * Creates the (private) subdirectory `$name` of the root directory if it does not exist yet, and the root
     * directory too.
     *
     * @return string The path of the subdirectory
     * @throws RuntimeException if a directory cannot be created or the root directory must not be used
     */
    public function createSubdirectory(string $name): string
    {
        $this->ensureExists();
        $directory = $this->path . DIRECTORY_SEPARATOR . $name;
        if (!is_dir(filename: $directory)) {
            if (!mkdir(directory: $directory, permissions: UploadDirectory::DIRECTORY_PERMISSIONS)
                && !is_dir(filename: $directory)) {
                throw new RuntimeException(message: 'The upload directory ' . $directory . ' cannot be created.');
            }
            $this->makePrivate(path: $directory);
        }

        return $directory;
    }

    /**
     * Makes a stored file readable and writable for the user of the PHP process only.
     *
     * @throws RuntimeException if the permissions cannot be set
     */
    public function protectFile(string $path): void
    {
        if (!chmod(filename: $path, permissions: UploadDirectory::FILE_PERMISSIONS)) {
            throw new RuntimeException(message: 'The permissions of the uploaded file cannot be set.');
        }
    }

    private function makePrivate(string $path): void
    {
        if (!chmod(filename: $path, permissions: UploadDirectory::DIRECTORY_PERMISSIONS)) {
            throw new RuntimeException(message: 'The permissions of the upload directory ' . $path . ' cannot be set.');
        }
    }

    private function assertUsable(): void
    {
        if (is_link(filename: $this->path)) {
            throw new RuntimeException(message: 'The upload directory ' . $this->path . ' is a symbolic link.');
        }
        if (!is_dir(filename: $this->path)) {
            throw new RuntimeException(message: 'The upload directory ' . $this->path . ' is no directory.');
        }
        if (fileowner(filename: $this->path) !== UploadDirectory::getCurrentUserId()) {
            throw new RuntimeException(
                message: 'The upload directory ' . $this->path . ' does not belong to the user of the PHP process.',
            );
        }
    }

    /**
     * The effective user of the process (`getmyuid()` is the owner of the script, not of the process).
     */
    private static function getCurrentUserId(): int
    {
        $userId = function_exists(function: 'posix_geteuid') ? posix_geteuid() : getmyuid();
        if ($userId === false) {
            throw new RuntimeException(message: 'The user of the PHP process cannot be determined.');
        }

        return $userId;
    }
}
