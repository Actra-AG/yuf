<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\core;

/**
 * The redirect to the login page and back: `RouteCollection::$loginPath` is the page, the query parameter
 * `RETURN_PARAMETER` carries the requested URI. Only local paths are accepted as target, so the login page cannot be
 * used for an open redirect (`//example.com`, `https://example.com`, `/\example.com`).
 */
final readonly class LoginRedirect
{
    public const string RETURN_PARAMETER = 'returnTo';

    /**
     * Whether the URI is a local path: it starts with a single "/" and has no backslash, white space or control
     * characters (a browser would turn `/\example.com` into `//example.com`).
     */
    public static function isLocalPath(string $uri): bool
    {
        return preg_match(pattern: '#^/(?![/\\\\])[^\\\\\\x00-\\x20\\x7f]*$#D', subject: $uri) === 1;
    }

    /**
     * The login path with the requested URI as return target (not added if it is no local path).
     */
    public static function createLoginUri(string $loginPath, string $returnUri): string
    {
        if (!LoginRedirect::isLocalPath(uri: $returnUri)) {
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
}
