<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use Override;

/**
 * Looks up the host name of the server address (reverse DNS) and falls back to the address.
 */
final readonly class ReverseDnsServerNameResolver implements ServerNameResolver
{
    private const string FALLBACK_HOST_NAME = 'localhost';

    #[Override]
    public function resolve(string $serverAddress): string
    {
        if (filter_var(value: $serverAddress, filter: FILTER_VALIDATE_IP, options: FILTER_NULL_ON_FAILURE) === null) {
            return ReverseDnsServerNameResolver::FALLBACK_HOST_NAME;
        }

        return ReverseDnsServerNameResolver::chooseServerName(
            lookupResult: gethostbyaddr(ip: $serverAddress),
            serverAddress: $serverAddress,
        );
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
