<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\common\FileCache;
use Override;

/**
 * Looks up the host name of the server address (reverse DNS) and falls back to the address. The lookup has no timeout
 * of its own: with a `FileCache` it runs once a day instead of once per mailer.
 */
final readonly class ReverseDnsServerNameResolver implements ServerNameResolver
{
    private const string FALLBACK_HOST_NAME = 'localhost';
    private const int CACHE_LIFETIME_IN_SECONDS = 86400;

    /**
     * @param ?FileCache $cache `$core->fileCache`; `null` looks the name up for every mailer
     */
    public function __construct(private ?FileCache $cache) {}

    #[Override]
    public function resolve(string $serverAddress): string
    {
        if (filter_var(value: $serverAddress, filter: FILTER_VALIDATE_IP, options: FILTER_NULL_ON_FAILURE) === null) {
            return ReverseDnsServerNameResolver::FALLBACK_HOST_NAME;
        }
        $cacheKey = 'yuf-server-name|' . $serverAddress;
        $cachedName = $this->cache?->get(key: $cacheKey);
        if ($cachedName !== null) {
            return $cachedName;
        }
        $serverName = ReverseDnsServerNameResolver::chooseServerName(
            lookupResult: gethostbyaddr(ip: $serverAddress),
            serverAddress: $serverAddress,
        );
        $this->cache?->set(
            key: $cacheKey,
            value: $serverName,
            lifetimeInSeconds: ReverseDnsServerNameResolver::CACHE_LIFETIME_IN_SECONDS,
        );

        return $serverName;
    }

    /**
     * A reverse DNS entry is controlled by whoever owns the address range: only a plain host name is used, otherwise
     * the address (the name goes into headers and into the `EHLO` command).
     *
     * @param string|false $lookupResult result of `gethostbyaddr()`
     */
    public static function chooseServerName(string|false $lookupResult, string $serverAddress): string
    {
        if (
            $lookupResult !== false
            && preg_match(pattern: '/^[A-Za-z0-9]([A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/D', subject: $lookupResult) === 1
        ) {
            return $lookupResult;
        }

        return $serverAddress;
    }
}
