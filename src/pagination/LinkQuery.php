<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\pagination;

/**
 * The query string of the links of pagination and sort links: the own parameter first, then the additional
 * parameters of the page (URL encoded, so the result is safe in an HTML attribute).
 *
 * @internal
 */
final readonly class LinkQuery
{
    /**
     * @param string $value Output as it is; the caller encodes the parts that are not fixed
     * @param array<string, string> $additionalParameters A parameter with the name of the own one is ignored
     */
    public static function create(string $name, string $value, array $additionalParameters): string
    {
        unset($additionalParameters[$name]);
        $query = '?' . $name . '=' . $value;
        if ($additionalParameters === []) {
            return $query;
        }

        return $query . '&' . http_build_query(data: $additionalParameters, arg_separator: '&');
    }
}
