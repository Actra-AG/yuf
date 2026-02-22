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
    public static function generateAbsoluteUri(string $relativeOrAbsoluteUri): string
    {
        $components = parse_url(url: $relativeOrAbsoluteUri);
        if (!array_key_exists(key: 'host', array: $components)) {
            if (str_starts_with(haystack: $relativeOrAbsoluteUri, needle: '/')) {
                $directory = '';
            } else {
                $directory = dirname(path: HttpRequest::getURI());
                $directory = ($directory === '/' || $directory === '\\') ? '/' : $directory . '/';
            }
            $absoluteUri = HttpRequest::getProtocol() . '://' . HttpRequest::getHost(
                ) . $directory . $relativeOrAbsoluteUri;
        } else {
            $absoluteUri = $relativeOrAbsoluteUri;
        }

        return $absoluteUri;
    }
}