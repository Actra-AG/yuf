<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

use InvalidArgumentException;

/**
 * The redirect to the login page and back: `RouteCollection::$loginTarget` is the page, the query parameter
 * `RETURN_PARAMETER` carries the requested URI. Only local paths (`findReturnPath()`) or absolute `https` URLs of
 * explicitly allowed hosts (`findReturnUrl()`) are accepted as target, so the login page cannot be used for an open
 * redirect (`//example.com`, `https://example.com`, `/\example.com`).
 */
final readonly class LoginRedirect
{
    public const string RETURN_PARAMETER = 'returnTo';

    /**
     * Whether the URI is a local path: it starts with a single "/" and has no backslash, white space or control
     * characters (a browser would turn `/\example.com` into `//example.com`). It must also parse as URI without
     * scheme and host (`parse_url()`).
     */
    public static function isLocalPath(string $uri): bool
    {
        if (preg_match(pattern: '#^/(?![/\\\\])[^\\\\\\x00-\\x20\\x7f]*$#D', subject: $uri) !== 1) {
            return false;
        }
        $parts = parse_url(url: $uri);

        return $parts !== false
            && !array_key_exists(key: 'scheme', array: $parts)
            && !array_key_exists(key: 'host', array: $parts);
    }

    /**
     * The lowercase host of an absolute URL `https://<host><local path>`, `null` for anything else: another scheme
     * (also `HTTPS:`), user info, a port, a host that is no ASCII host name (IDN in Unicode, `%`, a trailing dot,
     * empty labels) or a path that is no local path (`LoginRedirect::isLocalPath()`). Punycode hosts (`xn--…`) are
     * ASCII host names.
     */
    public static function findHttpsHost(string $url): ?string
    {
        if (preg_match(pattern: '#^https://([A-Za-z0-9.-]+)(/.*)$#Ds', subject: $url, matches: $matches) !== 1) {
            return null;
        }
        $host = strtolower(string: $matches[1]);
        if (!LoginRedirect::isHostName(host: $host) || !LoginRedirect::isLocalPath(uri: $matches[2])) {
            return null;
        }
        $parts = parse_url(url: $url);
        if (
            $parts === false
            || strtolower(string: $parts['host'] ?? '') !== $host
            || array_key_exists(key: 'port', array: $parts)
            || array_key_exists(key: 'user', array: $parts)
            || array_key_exists(key: 'pass', array: $parts)
        ) {
            return null;
        }

        return $host;
    }

    /**
     * Whether the URI is a valid return target: a local path or an absolute `https` URL (`findHttpsHost()`) whose
     * host is one of the allowed hosts (exact match, case-insensitive; no subdomains, no port).
     *
     * @param list<string> $allowedHosts Lowercase host names (`site-a.example.com`), not URLs
     *
     * @throws InvalidArgumentException if an allowed host is no lowercase host name
     */
    public static function isAllowedReturnUri(string $uri, array $allowedHosts): bool
    {
        foreach ($allowedHosts as $allowedHost) {
            if (!LoginRedirect::isHostName(host: $allowedHost)) {
                throw new InvalidArgumentException(message: 'Invalid allowed host: ' . $allowedHost);
            }
        }
        if (LoginRedirect::isLocalPath(uri: $uri)) {
            return true;
        }
        $host = LoginRedirect::findHttpsHost(url: $uri);

        return $host !== null && in_array(needle: $host, haystack: $allowedHosts, strict: true);
    }

    /**
     * The login path with the requested URI as return target, not added if it is no local path or URL of an allowed
     * host (`isAllowedReturnUri()`). Also for the next step of a login that keeps the return target.
     *
     * @param list<string> $allowedHosts Lowercase host names of allowed absolute return URLs, `[]`: local paths only
     *
     * @throws InvalidArgumentException if an allowed host is no lowercase host name
     */
    public static function createLoginUri(string $loginPath, string $returnUri, array $allowedHosts = []): string
    {
        if (!LoginRedirect::isAllowedReturnUri(uri: $returnUri, allowedHosts: $allowedHosts)) {
            return $loginPath;
        }

        return $loginPath . (str_contains(haystack: $loginPath, needle: '?') ? '&' : '?')
            . LoginRedirect::RETURN_PARAMETER . '=' . rawurlencode(string: $returnUri);
    }

    /**
     * The validated return target of the request of the login page: the local path to redirect to after the login,
     * `null` if there is none or it is no local path (redirect to the default page then).
     */
    public static function findReturnPath(HttpRequest $httpRequest): ?string
    {
        $returnUri = $httpRequest->getQueryString(name: LoginRedirect::RETURN_PARAMETER);

        return $returnUri !== null && LoginRedirect::isLocalPath(uri: $returnUri) ? $returnUri : null;
    }

    /**
     * The validated return target of the request of a login page that serves other hosts too: a local path or an
     * absolute `https` URL of an allowed host (`isAllowedReturnUri()`), `null` if there is none or it is not allowed
     * (redirect to the default page then).
     *
     * @param list<string> $allowedHosts Lowercase host names (`site-a.example.com`) the login page may send visitors back to
     *
     * @throws InvalidArgumentException if an allowed host is no lowercase host name
     */
    public static function findReturnUrl(HttpRequest $httpRequest, array $allowedHosts): ?string
    {
        $returnUri = $httpRequest->getQueryString(name: LoginRedirect::RETURN_PARAMETER);
        if ($returnUri === null) {
            return null;
        }

        return LoginRedirect::isAllowedReturnUri(uri: $returnUri, allowedHosts: $allowedHosts) ? $returnUri : null;
    }

    /**
     * Whether the string is a lowercase ASCII host name: labels of letters, digits and inner hyphens (at most 63
     * characters), separated by single dots, at most 253 characters.
     */
    private static function isHostName(string $host): bool
    {
        return strlen(string: $host) <= 253
            && preg_match(
                pattern: '/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/D',
                subject: $host,
            ) === 1;
    }
}
