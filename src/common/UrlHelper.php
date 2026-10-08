<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\core\HttpRequest;
use InvalidArgumentException;

/**
 * Stateless URL helper (static on purpose: it only builds a string from its arguments).
 */
final class UrlHelper
{
    /**
     * Makes a relative URI absolute with the protocol, the host and (for a URI without a leading "/") the directory
     * of the given request; a URI with a host gets the protocol of the request if it starts with "//" and stays as
     * it is otherwise.
     *
     * It does not check the target: a URI with a foreign host stays a link to this host, so never pass an
     * unchecked value from the user for a redirect (open redirect). Allow only relative paths or whitelisted hosts.
     *
     * @throws InvalidArgumentException If the URI cannot be parsed
     */
    public static function generateAbsoluteUri(string $relativeOrAbsoluteUri, HttpRequest $httpRequest): string
    {
        $components = parse_url(url: $relativeOrAbsoluteUri);
        if ($components === false) {
            throw new InvalidArgumentException(
                message: 'The URI "' . $relativeOrAbsoluteUri . '" cannot be parsed.',
            );
        }
        if (array_key_exists(key: 'host', array: $components)) {
            return str_starts_with(haystack: $relativeOrAbsoluteUri, needle: '//')
                ? $httpRequest->getProtocol()->value . ':' . $relativeOrAbsoluteUri
                : $relativeOrAbsoluteUri;
        }

        return $httpRequest->getProtocol()->value . '://' . $httpRequest->getHost()
            . UrlHelper::resolveDirectory(relativeUri: $relativeOrAbsoluteUri, httpRequest: $httpRequest)
            . $relativeOrAbsoluteUri;
    }

    private static function resolveDirectory(string $relativeUri, HttpRequest $httpRequest): string
    {
        if (str_starts_with(haystack: $relativeUri, needle: '/')) {
            return '';
        }
        $directory = dirname(path: $httpRequest->getPath());

        return ($directory === '/' || $directory === '\\') ? '/' : $directory . '/';
    }
}
