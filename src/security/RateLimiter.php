<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use SensitiveParameter;

/**
 * Counts hits per key (e.g. `RateLimiter::ipBucket()` of the client IP, or a fixed key for all visitors) in fixed
 * windows and blocks a key whose limit is reached, without database: one file per key and limit
 * (`<hmac-sha256 of key and limit>`, readable by the owner only), counted under an exclusive lock (`flock()`), so
 * parallel requests never lose a hit. The HMAC with the secret keeps keys such as IP addresses out of the file names.
 *
 * Use a directory of the application that is not served (e.g. `$core->cacheDirectory . 'rateLimit'`) on a local
 * file system (`flock()` is not reliable on network file systems). Expired files are purged by `consume()` now and then
 * (`$purgeProbabilityPercent`), so no cron job is needed.
 */
final readonly class RateLimiter
{
    private const int DIRECTORY_MODE = 0o700;
    private const int FILE_MODE = 0o600;
    private const int MIN_SECRET_LENGTH = 32;
    // A file can be purged between opening and locking it; the lock is then taken again on the new file
    private const int MAX_LOCK_ATTEMPTS = 10;

    private string $directory;

    /**
     * @param string $directory Created on the first hit if it does not exist
     * @param string $secret At least 32 bytes, e.g. from `.env.php`; changing it resets all counters
     * @param int $purgeProbabilityPercent Chance (0 to 100) that `consume()` calls `purgeExpired()`; 0 disables it
     *     (then purge with a cron job)
     *
     * @throws InvalidArgumentException if the secret is too short or the probability is out of range
     */
    public function __construct(
        string $directory,
        #[SensitiveParameter]
        private string $secret,
        private Clock $clock = new SystemClock(),
        private int $purgeProbabilityPercent = 1,
    ) {
        if (strlen(string: $secret) < RateLimiter::MIN_SECRET_LENGTH) {
            throw new InvalidArgumentException(
                message: 'The secret must be at least ' . RateLimiter::MIN_SECRET_LENGTH . ' bytes long.',
            );
        }
        if ($purgeProbabilityPercent < 0 || $purgeProbabilityPercent > 100) {
            throw new InvalidArgumentException(message: 'The purge probability must be between 0 and 100.');
        }
        $this->directory = rtrim(string: $directory, characters: '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * The bucket of an IP address for `consume()`: an IPv4 address itself, an IPv6 address as its /64 prefix
     * (`2001:db8:1:2::/64`; one connection usually gets a whole /64), an IPv4-mapped IPv6 address as IPv4.
     *
     * @throws InvalidArgumentException if the value is no IP address
     */
    public static function ipBucket(string $ipAddress): string
    {
        $binary = filter_var(value: $ipAddress, filter: FILTER_VALIDATE_IP) === false
            ? false
            : inet_pton(ip: $ipAddress);
        if ($binary === false) {
            // The value is not repeated: it may be personal data that ends up in a log
            throw new InvalidArgumentException(message: 'The value is no valid IP address.');
        }
        if (strlen(string: $binary) === 16) {
            $binary = str_starts_with(haystack: $binary, needle: str_repeat(string: "\0", times: 10) . "\xff\xff")
                ? substr(string: $binary, offset: 12)
                : substr(string: $binary, offset: 0, length: 8) . str_repeat(string: "\0", times: 8);
        }
        $bucket = inet_ntop(ip: $binary);
        if ($bucket === false) {
            throw new InvalidArgumentException(message: 'The value is no valid IP address.');
        }

        return strlen(string: $binary) === 16 ? $bucket . '/64' : $bucket;
    }

    /**
     * Counts one hit for the key in the current window of every limit, if none of them is reached yet. If one is
     * reached, nothing is counted (a blocked request uses up no other limit) and the result is not allowed. Check
     * the limits of a single client before global ones, in separate calls, so one client cannot use up the global
     * budget with blocked requests.
     *
     * @throws InvalidArgumentException if no limit is given
     * @throws RuntimeException if the directory or a file cannot be written; decide whether to block (fail closed)
     *     or allow the request then
     */
    public function consume(string $key, RateLimit ...$limits): RateLimitResult
    {
        if ($limits === []) {
            throw new InvalidArgumentException(message: 'At least one limit is needed.');
        }
        $now = $this->clock->now()->getTimestamp();
        $limitsByFilePath = [];
        foreach ($limits as $limit) {
            $limitsByFilePath[$this->getFilePath(key: $key, limit: $limit)] = $limit;
        }
        // Always locked in the same order, so parallel calls with overlapping limits never wait for each other forever
        ksort(array: $limitsByFilePath, flags: SORT_STRING);
        $handles = [];
        RateLimiter::suppressWarnings();
        try {
            $this->createDirectory();
            $counters = [];
            $retryAfterSeconds = 0;
            foreach ($limitsByFilePath as $filePath => $limit) {
                $handle = $this->openLocked(filePath: $filePath);
                $handles[] = $handle;
                $windowEnd = RateLimiter::getWindowEnd(now: $now, limit: $limit);
                $counter = $this->readCounter(handle: $handle);
                $hits = $counter !== null && $counter['windowEnd'] === $windowEnd ? $counter['hits'] : 0;
                if ($hits >= $limit->maxHits) {
                    $retryAfterSeconds = max($retryAfterSeconds, $windowEnd - $now);
                }
                $counters[] = ['handle' => $handle, 'windowEnd' => $windowEnd, 'hits' => $hits];
            }
            if ($retryAfterSeconds > 0) {
                $result = RateLimitResult::blocked(retryAfterSeconds: $retryAfterSeconds);
            } else {
                foreach ($counters as $counter) {
                    $this->writeCounter(
                        handle: $counter['handle'],
                        windowEnd: $counter['windowEnd'],
                        hits: $counter['hits'] + 1,
                    );
                }
                $result = RateLimitResult::allowed();
            }
        } finally {
            foreach ($handles as $handle) {
                fclose(stream: $handle);
            }
            restore_error_handler();
        }
        if (
            $this->purgeProbabilityPercent > 0
            && random_int(min: 1, max: 100) <= $this->purgeProbabilityPercent
        ) {
            $this->purgeExpired();
        }

        return $result;
    }

    /**
     * Deletes the files whose window has ended. Files in use by `consume()` at the same time are skipped.
     *
     * @param int $maxFiles Deletes at most this many files, so one call stays short
     *
     * @return int The number of deleted files
     *
     * @throws InvalidArgumentException if `$maxFiles` is less than 1
     * @throws RuntimeException if the directory cannot be read or a file cannot be deleted
     */
    public function purgeExpired(int $maxFiles = 1000): int
    {
        if ($maxFiles < 1) {
            throw new InvalidArgumentException(message: 'The maximum number of files must be at least 1.');
        }
        if (!is_dir(filename: $this->directory)) {
            return 0;
        }
        $now = $this->clock->now()->getTimestamp();
        $deleted = 0;
        RateLimiter::suppressWarnings();
        $directoryHandle = false;
        try {
            $directoryHandle = opendir(directory: $this->directory);
            if ($directoryHandle === false) {
                throw new RuntimeException(
                    message: 'Cannot read the rate limit directory "' . $this->directory . '".',
                );
            }
            while ($deleted < $maxFiles && ($fileName = readdir(dir_handle: $directoryHandle)) !== false) {
                if (preg_match(pattern: '/^[0-9a-f]{64}$/', subject: $fileName) !== 1) {
                    continue;
                }
                if ($this->deleteIfExpired(filePath: $this->directory . $fileName, now: $now)) {
                    $deleted++;
                }
            }
        } finally {
            if ($directoryHandle !== false) {
                closedir(dir_handle: $directoryHandle);
            }
            restore_error_handler();
        }

        return $deleted;
    }

    /**
     * A failed file operation reports its failure by its result, which throws a `RuntimeException`; its PHP warning
     * would become another exception in the error handler of the application. Restore with `restore_error_handler()`.
     */
    private static function suppressWarnings(): void
    {
        set_error_handler(callback: static fn(): bool => true);
    }

    private static function getWindowEnd(int $now, RateLimit $limit): int
    {
        return $now - $now % $limit->windowSeconds + $limit->windowSeconds;
    }

    /**
     * @throws RuntimeException
     */
    private function deleteIfExpired(string $filePath, int $now): bool
    {
        // "c+" does not fail for a file that a parallel purge has just deleted (the empty file is deleted below)
        $handle = fopen(filename: $filePath, mode: 'c+');
        if ($handle === false) {
            return false;
        }
        try {
            // A file locked by consume() is in use, so its window has not ended
            if (!flock(stream: $handle, operation: LOCK_EX | LOCK_NB) || RateLimiter::isUnlinked(handle: $handle)) {
                return false;
            }
            $counter = $this->readCounter(handle: $handle);
            if ($counter !== null && $counter['windowEnd'] > $now) {
                return false;
            }
            // Deleted while locked: consume() calls waiting for the lock notice it and lock the new file
            if (!unlink(filename: $filePath)) {
                throw new RuntimeException(message: 'Cannot delete the rate limit file "' . $filePath . '".');
            }

            return true;
        } finally {
            fclose(stream: $handle);
        }
    }

    /**
     * @return resource
     *
     * @throws RuntimeException
     */
    private function openLocked(string $filePath)
    {
        for ($attempt = 1; $attempt <= RateLimiter::MAX_LOCK_ATTEMPTS; $attempt++) {
            $handle = fopen(filename: $filePath, mode: 'c+');
            if ($handle === false) {
                throw new RuntimeException(message: 'Cannot open the rate limit file "' . $filePath . '".');
            }
            if (!flock(stream: $handle, operation: LOCK_EX)) {
                fclose(stream: $handle);
                throw new RuntimeException(message: 'Cannot lock the rate limit file "' . $filePath . '".');
            }
            // Purged between fopen() and flock(): this handle points to a deleted file, open the new one
            if (RateLimiter::isUnlinked(handle: $handle)) {
                fclose(stream: $handle);
                continue;
            }
            $stat = fstat(stream: $handle);
            if (
                $stat !== false
                && ($stat['mode'] & 0o777) !== RateLimiter::FILE_MODE
                && !chmod(filename: $filePath, permissions: RateLimiter::FILE_MODE)
            ) {
                fclose(stream: $handle);
                throw new RuntimeException(
                    message: 'Cannot restrict the access to the rate limit file "' . $filePath . '".',
                );
            }

            return $handle;
        }
        throw new RuntimeException(message: 'Cannot lock the rate limit file "' . $filePath . '".');
    }

    /**
     * @param resource $handle
     */
    private static function isUnlinked($handle): bool
    {
        $stat = fstat(stream: $handle);

        return $stat === false || $stat['nlink'] === 0;
    }

    /**
     * @param resource $handle
     *
     * @return ?array{windowEnd: int, hits: int} `null` for an empty (new) or damaged file
     */
    private function readCounter($handle): ?array
    {
        if (!rewind(stream: $handle)) {
            return null;
        }
        $content = stream_get_contents(stream: $handle);
        if ($content === false || $content === '') {
            return null;
        }
        try {
            $data = json_decode(json: $content, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (
            !is_array(value: $data)
            || !array_key_exists(key: 'windowEnd', array: $data)
            || !array_key_exists(key: 'hits', array: $data)
            || !is_int(value: $data['windowEnd'])
            || !is_int(value: $data['hits'])
        ) {
            return null;
        }

        return ['windowEnd' => $data['windowEnd'], 'hits' => $data['hits']];
    }

    /**
     * @param resource $handle
     *
     * @throws RuntimeException
     */
    private function writeCounter($handle, int $windowEnd, int $hits): void
    {
        $content = '{"windowEnd":' . $windowEnd . ',"hits":' . $hits . '}';
        if (
            !ftruncate(stream: $handle, size: 0)
            || !rewind(stream: $handle)
            || fwrite(stream: $handle, data: $content) !== strlen(string: $content)
            || !fflush(stream: $handle)
        ) {
            throw new RuntimeException(message: 'Cannot write to the rate limit directory "' . $this->directory . '".');
        }
    }

    private function getFilePath(string $key, RateLimit $limit): string
    {
        return $this->directory . hash_hmac(
            algo: 'sha256',
            data: $key . '|' . $limit->maxHits . '|' . $limit->windowSeconds,
            key: $this->secret,
        );
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
            !mkdir(directory: $this->directory, permissions: RateLimiter::DIRECTORY_MODE, recursive: true)
            && !is_dir(filename: $this->directory)
        ) {
            throw new RuntimeException(
                message: 'Cannot create the rate limit directory "' . $this->directory . '".',
            );
        }
    }
}
