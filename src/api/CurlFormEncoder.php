<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

use InvalidArgumentException;

/**
 * Encodes form data like a browser does (`application/x-www-form-urlencoded`, RFC 3986): booleans become `1` / `0`,
 * `null` an empty value, objects their public properties.
 *
 * @internal
 */
final class CurlFormEncoder
{
    /**
     * @param array<array-key, mixed> $postData Scalars, `null`, objects or nested arrays of them
     */
    public static function encode(array $postData): string
    {
        return http_build_query(
            data: CurlFormEncoder::convertArray(data: $postData),
            encoding_type: PHP_QUERY_RFC3986,
        );
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private static function convertArray(array $data): array
    {
        $converted = [];
        foreach ($data as $key => $value) {
            $converted[$key] = CurlFormEncoder::convertValue(value: $value);
        }

        return $converted;
    }

    /**
     * @param mixed $value Any value of a form array, narrowed here
     *
     * @return array<array-key, mixed>|string
     */
    private static function convertValue(mixed $value): array|string
    {
        return match (true) {
            is_bool(value: $value) => $value ? '1' : '0',
            is_string(value: $value) => $value,
            is_int(value: $value), is_float(value: $value) => (string) $value,
            $value === null => '',
            is_object(value: $value) => CurlFormEncoder::convertArray(data: get_object_vars(object: $value)),
            is_array(value: $value) => CurlFormEncoder::convertArray(data: $value),
            default => throw new InvalidArgumentException(
                message: 'Form data may only contain scalars, null, arrays and objects, ' . get_debug_type(value: $value)
                . ' found.',
            ),
        };
    }
}
