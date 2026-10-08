<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\exception\UnauthorizedException;
use OpenSSLAsymmetricKey;
use RuntimeException;

/**
 * The public keys of an identity provider, cached in a file. A key ID the cache does not know triggers a download,
 * but at most one per refresh interval, so a token with random key IDs cannot make the server download on every
 * request. A downloaded key set replaces the cache only if it parses (a broken download cannot lock everybody out);
 * the file is written atomically with mode 0600.
 *
 * @internal
 */
final readonly class CachedKeySet
{
    public function __construct(
        private string $cacheFilePath,
        private JsonWebKeySetSource $source,
        private Clock $clock = new SystemClock(),
        private int $refreshIntervalInSeconds = 300,
    ) {}

    /**
     * @throws UnauthorizedException if the provider has no key with this ID
     * @throws RuntimeException if the key set cannot be downloaded or the cache cannot be written
     */
    public function getKey(string $keyId): OpenSSLAsymmetricKey
    {
        $cachedKeys = $this->readCache();
        if ($cachedKeys !== null && array_key_exists(key: $keyId, array: $cachedKeys)) {
            return $cachedKeys[$keyId];
        }
        if ($cachedKeys !== null && !$this->isRefreshAllowed()) {
            throw new UnauthorizedException(message: 'Unknown key ID');
        }
        $json = $this->source->download();
        $keys = JsonWebKeySetParser::parse(json: $json);
        $this->writeCache(json: $json);
        if (!array_key_exists(key: $keyId, array: $keys)) {
            throw new UnauthorizedException(message: 'Unknown key ID');
        }

        return $keys[$keyId];
    }

    /**
     * @return ?non-empty-array<string, OpenSSLAsymmetricKey> `null` if there is no usable cache
     */
    private function readCache(): ?array
    {
        if (!is_file(filename: $this->cacheFilePath)) {
            return null;
        }
        $json = file_get_contents(filename: $this->cacheFilePath);
        if ($json === false) {
            return null;
        }
        try {
            return JsonWebKeySetParser::parse(json: $json);
        } catch (UnauthorizedException) {
            return null;
        }
    }

    private function isRefreshAllowed(): bool
    {
        $modified = filemtime(filename: $this->cacheFilePath);

        return $modified === false
            || $this->clock->now()->getTimestamp() - $modified >= $this->refreshIntervalInSeconds;
    }

    /**
     * @throws RuntimeException
     */
    private function writeCache(string $json): void
    {
        $directory = dirname(path: $this->cacheFilePath);
        if (!is_dir(filename: $directory)) {
            throw new RuntimeException(message: 'The cache directory "' . $directory . '" does not exist.');
        }
        $temporaryPath = tempnam(directory: $directory, prefix: 'keys');
        if ($temporaryPath === false) {
            throw new RuntimeException(message: 'Cannot create a file in the cache directory "' . $directory . '".');
        }
        try {
            if (file_put_contents(filename: $temporaryPath, data: $json) === false
                || !rename(from: $temporaryPath, to: $this->cacheFilePath)) {
                throw new RuntimeException(message: 'Cannot write the key cache "' . $this->cacheFilePath . '".');
            }
        } finally {
            if (is_file(filename: $temporaryPath)) {
                unlink(filename: $temporaryPath);
            }
        }
    }
}
