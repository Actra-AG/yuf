<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\clock\Clock;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\LoginRedirect;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * The directory and the browser binding that `LoginHandOver` and `SharedLogin` share: records are JSON files named by
 * the SHA-256 hash of their random ID (`<prefix>-<hash>.json`), written atomically, used up by an atomic rename; the
 * modification time is the expiry. Browsers are bound by a random secret in the cookie `__Host-yuf-login-browser`
 * (only its hash is stored).
 *
 * @internal Used by `LoginHandOver` and `SharedLogin`; not part of the public API.
 */
final readonly class LoginHandOverStore
{
    public const string BROWSER_COOKIE_NAME = '__Host-yuf-login-browser';
    public const int BROWSER_COOKIE_LIFETIME_IN_SECONDS = 900;
    private const int ID_BYTES = 32;
    private const string ID_PATTERN = '/^[0-9a-f]{64}$/D';
    private const string PREFIX_PATTERN = '/^[a-z]+$/D';
    private const string FILE_PATTERN = '/^[a-z]+-[0-9a-f]{64}\.json(?:\.[0-9a-f]{16}\.claimed)?$'
        . '|^\.tmp-[0-9a-f]{16}$/D';
    private const int DIRECTORY_MODE = 0o700;
    private const int FILE_MODE = 0o600;
    private const int MAX_PURGED_FILES = 1000;

    private string $directory;

    /**
     * @throws InvalidArgumentException if the purge probability is not between 0 and 100
     */
    public function __construct(
        string $directory,
        private Clock $clock,
        private SsoNonceCookieWriter $cookieWriter,
        private int $purgeProbabilityPercent,
    ) {
        if ($purgeProbabilityPercent < 0 || $purgeProbabilityPercent > 100) {
            throw new InvalidArgumentException(message: 'The purge probability must be between 0 and 100.');
        }
        $this->directory = rtrim(string: $directory, characters: '/\\') . DIRECTORY_SEPARATOR;
    }

    public static function createId(): string
    {
        return bin2hex(string: random_bytes(length: LoginHandOverStore::ID_BYTES));
    }

    /**
     * Whether the string has the format of an ID (64 lowercase hex characters).
     */
    public static function isId(string $id): bool
    {
        return preg_match(pattern: LoginHandOverStore::ID_PATTERN, subject: $id) === 1;
    }

    public static function hash(string $secret): string
    {
        return hash(algo: 'sha256', data: $secret);
    }

    public static function isHostName(string $host): bool
    {
        return LoginRedirect::findHttpsHost(url: 'https://' . $host . '/') === $host;
    }

    /**
     * @param list<string> $allowedHosts
     *
     * @throws InvalidArgumentException if an allowed host is no lowercase host name
     */
    public static function assertAllowedHosts(array $allowedHosts): void
    {
        foreach ($allowedHosts as $allowedHost) {
            if (!LoginHandOverStore::isHostName(host: $allowedHost)) {
                throw new InvalidArgumentException(message: 'Invalid allowed host: ' . $allowedHost);
            }
        }
    }

    /**
     * Whether the path is a local path without query (a hand-over path).
     */
    public static function isPathWithoutQuery(string $path): bool
    {
        return LoginRedirect::isLocalPath(uri: $path) && !str_contains(haystack: $path, needle: '?');
    }

    /**
     * @param ?array<string, string|int|null> $record
     */
    public static function readString(?array $record, string $key): ?string
    {
        $value = $record[$key] ?? null;

        return is_string(value: $value) ? $value : null;
    }

    /**
     * @param ?array<string, string|int|null> $record
     */
    public static function readInt(?array $record, string $key): ?int
    {
        $value = $record[$key] ?? null;

        return is_int(value: $value) ? $value : null;
    }

    public function now(): int
    {
        return $this->clock->now()->getTimestamp();
    }

    /**
     * The hash of the browser secret of the cookie if it is valid, else of a new one; the cookie is sent (again) in
     * both cases, so hand-overs in several tabs share it.
     */
    public function bindBrowser(HttpRequest $httpRequest): string
    {
        $secret = $httpRequest->getCookie(name: LoginHandOverStore::BROWSER_COOKIE_NAME);
        if ($secret === null || !LoginHandOverStore::isId(id: $secret)) {
            $secret = LoginHandOverStore::createId();
        }
        $this->cookieWriter->set(
            name: LoginHandOverStore::BROWSER_COOKIE_NAME,
            value: $secret,
            maxAgeInSeconds: LoginHandOverStore::BROWSER_COOKIE_LIFETIME_IN_SECONDS,
            path: '/',
        );

        return LoginHandOverStore::hash(secret: $secret);
    }

    /**
     * Whether the browser cookie of the request has the secret of the hash.
     */
    public function isBrowser(HttpRequest $httpRequest, ?string $browserHash): bool
    {
        $secret = $httpRequest->getCookie(name: LoginHandOverStore::BROWSER_COOKIE_NAME);

        return $browserHash !== null
            && $secret !== null
            && LoginHandOverStore::isId(id: $secret)
            && hash_equals(known_string: $browserHash, user_string: LoginHandOverStore::hash(secret: $secret));
    }

    public function setCookie(string $name, string $value, int $maxAgeInSeconds): void
    {
        $this->cookieWriter->set(name: $name, value: $value, maxAgeInSeconds: $maxAgeInSeconds, path: '/');
    }

    /**
     * Deletes expired files with the purge probability of the constructor.
     */
    public function purgeByChance(): void
    {
        if ($this->purgeProbabilityPercent > 0 && random_int(min: 1, max: 100) <= $this->purgeProbabilityPercent) {
            $this->purgeExpired();
        }
    }

    /**
     * Deletes expired records (and left-overs of interrupted writes), at most 1000 per call.
     *
     * @return int The number of deleted files
     */
    public function purgeExpired(): int
    {
        if (!is_dir(filename: $this->directory)) {
            return 0;
        }
        $now = $this->now();
        $purged = 0;
        set_error_handler(callback: static fn(): bool => true);
        try {
            $directoryHandle = opendir(directory: $this->directory);
            if ($directoryHandle === false) {
                return 0;
            }
            clearstatcache();
            while (
                $purged < LoginHandOverStore::MAX_PURGED_FILES
                && ($fileName = readdir(dir_handle: $directoryHandle)) !== false
            ) {
                if (preg_match(pattern: LoginHandOverStore::FILE_PATTERN, subject: $fileName) !== 1) {
                    continue;
                }
                $expiresAt = filemtime(filename: $this->directory . $fileName);
                if ($expiresAt !== false && $expiresAt <= $now && unlink(filename: $this->directory . $fileName)) {
                    $purged++;
                }
            }
            closedir(dir_handle: $directoryHandle);
        } finally {
            restore_error_handler();
        }

        return $purged;
    }

    /**
     * Stores the record (with `expiresAt`) under the ID, replacing a record of the same ID.
     *
     * @param array<string, string|int|null> $record
     *
     * @throws InvalidArgumentException if a value is no valid UTF-8
     * @throws RuntimeException if the directory cannot be written
     */
    public function write(string $prefix, string $id, array $record, int $expiresAt): void
    {
        try {
            $json = json_encode(value: [...$record, 'expiresAt' => $expiresAt], flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException(message: 'The login hand-over data is no valid UTF-8.');
        }
        $temporaryPath = $this->directory . '.tmp-' . bin2hex(string: random_bytes(length: 8));
        set_error_handler(callback: static fn(): bool => true);
        try {
            $this->createDirectory();
            if (
                file_put_contents(filename: $temporaryPath, data: $json) !== strlen(string: $json)
                || !chmod(filename: $temporaryPath, permissions: LoginHandOverStore::FILE_MODE)
                || !touch(filename: $temporaryPath, mtime: $expiresAt)
                || !rename(from: $temporaryPath, to: $this->directory . LoginHandOverStore::fileName(
                    prefix: $prefix,
                    id: $id,
                ))
            ) {
                throw new RuntimeException(message: 'Cannot write to the directory "' . $this->directory . '".');
            }
        } finally {
            if (is_file(filename: $temporaryPath)) {
                unlink(filename: $temporaryPath);
            }
            restore_error_handler();
        }
    }

    /**
     * The record of the ID, `null` if there is none (also for an ID of another format). Expiry is not checked.
     *
     * @return ?array<string, string|int|null>
     */
    public function read(string $prefix, string $id): ?array
    {
        if (!LoginHandOverStore::isId(id: $id)) {
            return null;
        }
        set_error_handler(callback: static fn(): bool => true);
        try {
            $json = file_get_contents(filename: $this->directory . LoginHandOverStore::fileName(
                prefix: $prefix,
                id: $id,
            ));
        } finally {
            restore_error_handler();
        }

        return $json === false ? null : LoginHandOverStore::decode(json: $json);
    }

    /**
     * Reads the record of the ID and deletes it, atomically: of two parallel calls only one gets the record. Expiry is
     * not checked.
     *
     * @return ?array<string, string|int|null>
     */
    public function claim(string $prefix, string $id): ?array
    {
        if (!LoginHandOverStore::isId(id: $id)) {
            return null;
        }
        $path = $this->directory . LoginHandOverStore::fileName(prefix: $prefix, id: $id);
        $claimedPath = $path . '.' . bin2hex(string: random_bytes(length: 8)) . '.claimed';
        set_error_handler(callback: static fn(): bool => true);
        try {
            if (!rename(from: $path, to: $claimedPath)) {
                return null;
            }
            $json = file_get_contents(filename: $claimedPath);
            unlink(filename: $claimedPath);
        } finally {
            restore_error_handler();
        }

        return $json === false ? null : LoginHandOverStore::decode(json: $json);
    }

    /**
     * Whether the record is not expired (`expiresAt` in the future).
     *
     * @param ?array<string, string|int|null> $record
     */
    public function isUnexpired(?array $record): bool
    {
        $expiresAt = LoginHandOverStore::readInt(record: $record, key: 'expiresAt');

        return $expiresAt !== null && $expiresAt > $this->now();
    }

    /**
     * @throws InvalidArgumentException if the prefix is no lowercase word
     */
    private static function fileName(string $prefix, string $id): string
    {
        if (preg_match(pattern: LoginHandOverStore::PREFIX_PATTERN, subject: $prefix) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid record prefix: ' . $prefix);
        }

        return $prefix . '-' . LoginHandOverStore::hash(secret: $id) . '.json';
    }

    /**
     * @return ?array<string, string|int|null>
     */
    private static function decode(string $json): ?array
    {
        try {
            $data = json_decode(json: $json, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
        if (!is_array(value: $data)) {
            return null;
        }
        $record = [];
        foreach ($data as $key => $value) {
            if (!is_string(value: $key) || !(is_string(value: $value) || is_int(value: $value) || $value === null)) {
                return null;
            }
            $record[$key] = $value;
        }

        return $record;
    }

    /**
     * Creates the directory (`0700`) if it is missing; refuses one that group or others may write.
     *
     * @throws RuntimeException
     */
    private function createDirectory(): void
    {
        if (is_dir(filename: $this->directory)) {
            clearstatcache(clear_realpath_cache: false, filename: $this->directory);
            $permissions = fileperms(filename: $this->directory);
            if ($permissions === false || ($permissions & 0o022) !== 0) {
                throw new RuntimeException(
                    message: 'The directory "' . $this->directory . '" must not be writable by group or others.',
                );
            }

            return;
        }
        if (
            !mkdir(directory: $this->directory, permissions: LoginHandOverStore::DIRECTORY_MODE, recursive: true)
            && !is_dir(filename: $this->directory)
        ) {
            throw new RuntimeException(message: 'Cannot create the directory "' . $this->directory . '".');
        }
    }
}
