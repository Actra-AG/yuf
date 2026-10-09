<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Keeps string values with a lifetime in files of one directory, so results that are expensive to get (an access
 * token, a DNS lookup) survive the request. One file per key (`<sha256 of the key>.json`, readable by the owner only),
 * written atomically (temporary file, then `rename()`), so parallel requests never read a half-written value.
 *
 * Use a directory of the application that is not served (e.g. `$core->cacheDirectory . 'values'`). Never cache
 * personal data; a key contains everything the value depends on.
 */
final readonly class FileCache
{
    private const int DIRECTORY_MODE = 0o700;
    private const int FILE_MODE = 0o600;

    private string $directory;

    /**
     * @param string $directory Created on the first write if it does not exist
     */
    public function __construct(string $directory, private Clock $clock = new SystemClock())
    {
        $this->directory = rtrim(string: $directory, characters: '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * The value of the key, `null` if there is none or it expired (or the file cannot be read).
     */
    public function get(string $key): ?string
    {
        $filePath = $this->getFilePath(key: $key);
        $content = is_file(filename: $filePath) ? file_get_contents(filename: $filePath) : false;
        if ($content === false) {
            return null;
        }
        try {
            $data = json_decode(json: $content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (
            !is_array(value: $data)
            || !array_key_exists(key: 'expiresAt', array: $data)
            || !array_key_exists(key: 'value', array: $data)
            || !is_int(value: $data['expiresAt'])
            || !is_string(value: $data['value'])
            || $data['expiresAt'] <= $this->clock->now()->getTimestamp()
        ) {
            return null;
        }

        return $data['value'];
    }

    /**
     * @throws InvalidArgumentException if the lifetime is less than 1 second
     * @throws RuntimeException if the directory or the file cannot be written
     */
    public function set(string $key, string $value, int $lifetimeInSeconds): void
    {
        if ($lifetimeInSeconds < 1) {
            throw new InvalidArgumentException(message: 'The lifetime must be at least 1 second.');
        }
        $this->createDirectory();
        $temporaryPath = tempnam(directory: $this->directory, prefix: 'cache');
        if ($temporaryPath === false) {
            throw new RuntimeException(
                message: 'Cannot create a file in the cache directory "' . $this->directory . '".',
            );
        }
        try {
            $json = json_encode(
                value: ['expiresAt' => $this->clock->now()->getTimestamp() + $lifetimeInSeconds, 'value' => $value],
                flags: JSON_THROW_ON_ERROR,
            );
            if (
                file_put_contents(filename: $temporaryPath, data: $json) === false
                || !chmod(filename: $temporaryPath, permissions: FileCache::FILE_MODE)
                || !rename(from: $temporaryPath, to: $this->getFilePath(key: $key))
            ) {
                throw new RuntimeException(
                    message: 'Cannot write to the cache directory "' . $this->directory . '".',
                );
            }
        } catch (JsonException) {
            throw new RuntimeException(message: 'The value cannot be cached: it is no valid UTF-8.');
        } finally {
            if (is_file(filename: $temporaryPath)) {
                unlink(filename: $temporaryPath);
            }
        }
    }

    public function delete(string $key): void
    {
        $filePath = $this->getFilePath(key: $key);
        if (is_file(filename: $filePath)) {
            unlink(filename: $filePath);
        }
    }

    private function getFilePath(string $key): string
    {
        return $this->directory . hash(algo: 'sha256', data: $key) . '.json';
    }

    /**
     * @throws RuntimeException
     */
    private function createDirectory(): void
    {
        if (is_dir(filename: $this->directory)) {
            return;
        }
        if (
            !mkdir(directory: $this->directory, permissions: FileCache::DIRECTORY_MODE, recursive: true)
            && !is_dir(filename: $this->directory)
        ) {
            throw new RuntimeException(message: 'Cannot create the cache directory "' . $this->directory . '".');
        }
    }
}
