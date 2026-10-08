<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use actra\yuf\core\HttpRequest;

class UrlHelper
{
    /**
     * Makes a relative URI absolute with the protocol, the host and (for a URI without a leading "/") the directory
     * of the given request; an absolute URI stays as it is.
     */
    public static function generateAbsoluteUri(string $relativeOrAbsoluteUri, HttpRequest $httpRequest): string
    {
        $components = parse_url(url: $relativeOrAbsoluteUri);
        if (!array_key_exists(key: 'host', array: $components)) {
            if (str_starts_with(haystack: $relativeOrAbsoluteUri, needle: '/')) {
                $directory = '';
            } else {
                $directory = dirname(path: $httpRequest->getPath());
                $directory = ($directory === '/' || $directory === '\\') ? '/' : $directory . '/';
            }
            $absoluteUri = $httpRequest->getProtocol()->value . '://' . $httpRequest->getHost()
                . $directory . $relativeOrAbsoluteUri;
        } else {
            $absoluteUri = $relativeOrAbsoluteUri;
        }

        return $absoluteUri;
    }
}
