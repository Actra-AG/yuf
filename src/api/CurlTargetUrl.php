<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use InvalidArgumentException;

/**
 * The target of a request: an absolute http or https URL without user name and password (credentials belong to the
 * authentication methods of the request, not into a URL that ends up in logs). The message of the exception never
 * contains the URL, because a query string may carry a token.
 *
 * @internal
 */
final readonly class CurlTargetUrl
{
    public string $url;
    public string $host;
    public bool $isHttps;

    public function __construct(string $url)
    {
        // Control characters, spaces and backslashes are no valid parts of a URL and are read differently by parsers
        $parts = preg_match(pattern: '/[\x00-\x20\x7F\\\\]/', subject: $url) === 1 ? false : parse_url(url: $url);
        if (
            $parts === false
            || !array_key_exists(key: 'scheme', array: $parts)
            || !array_key_exists(key: 'host', array: $parts)
            || $parts['host'] === ''
            || array_key_exists(key: 'user', array: $parts)
            || array_key_exists(key: 'pass', array: $parts)
        ) {
            throw new InvalidArgumentException(
                message: 'The request target must be an absolute http:// or https:// URL with a host name and'
                . ' without user name and password.',
            );
        }
        $scheme = strtolower(string: $parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new InvalidArgumentException(
                message: 'The request target must use the scheme http or https.',
            );
        }
        $this->url = $url;
        $this->host = strtolower(string: $parts['host']);
        $this->isHttps = $scheme === 'https';
    }

    public function isLoopback(): bool
    {
        if ($this->host === 'localhost' || $this->host === '[::1]') {
            return true;
        }

        return filter_var(value: $this->host, filter: FILTER_VALIDATE_IP, options: FILTER_FLAG_IPV4) !== false
            && str_starts_with(haystack: $this->host, needle: '127.');
    }

    /**
     * Credentials must not cross the network unencrypted: only HTTPS, or a server on this machine.
     */
    public function isSafeForCredentials(): bool
    {
        return $this->isHttps || $this->isLoopback();
    }
}
