<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use JsonException;
use RuntimeException;
use stdClass;
use UnexpectedValueException;

/**
 * Pure and stateless helpers (static on purpose): JSON encoding and decoding with the flags of the framework and
 * reading of JSON files with comments.
 */
final class JsonUtils
{
    private const string WHITESPACE = " \t\n\r\v\f";

    /**
     * @param mixed $valueToConvert Any value `json_encode()` can encode (the data is open on purpose)
     *
     * @throws JsonException If the value cannot be encoded (e.g. invalid UTF-8)
     */
    public static function convertToJsonString(mixed $valueToConvert): string
    {
        return json_encode(
            value: $valueToConvert,
            flags: JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param bool $isMinified `false` if the file may contain whitespace and comments (`//` and block comments)
     *
     * @return stdClass|array<array-key, mixed>
     *
     * @throws RuntimeException If the file does not exist or cannot be read
     * @throws JsonException If the content is no valid JSON
     * @throws UnexpectedValueException If the JSON is no object and no array
     */
    public static function decodeFile(
        string $filePath,
        bool $isMinified,
        bool $returnAssociativeArray = false,
    ): stdClass|array {
        if (!is_file(filename: $filePath)) {
            throw new RuntimeException(message: 'The JSON file does not exist: ' . $filePath);
        }
        $jsonString = file_get_contents(filename: $filePath);
        if ($jsonString === false) {
            throw new RuntimeException(message: 'The JSON file cannot be read: ' . $filePath);
        }
        if (!$isMinified && $jsonString !== '{}') {
            $jsonString = JsonUtils::minify(jsonString: $jsonString);
        }

        return JsonUtils::decodeJsonString(jsonString: $jsonString, returnAssociativeArray: $returnAssociativeArray);
    }

    /**
     * Removes the whitespace outside of strings and the comments (`//` until the end of the line, block comments).
     */
    public static function minify(string $jsonString): string
    {
        $minified = '';
        $length = strlen(string: $jsonString);
        $position = 0;
        while ($position < $length) {
            $character = $jsonString[$position];
            $twoCharacters = substr(string: $jsonString, offset: $position, length: 2);
            if ($character === '"') {
                $stringEnd = JsonUtils::findEndOfString(jsonString: $jsonString, startPosition: $position);
                $minified .= substr(string: $jsonString, offset: $position, length: $stringEnd - $position);
                $position = $stringEnd;
            } elseif ($twoCharacters === '//') {
                $position += strcspn(string: $jsonString, characters: "\r\n", offset: $position);
            } elseif ($twoCharacters === '/*') {
                $commentEnd = strpos(haystack: $jsonString, needle: '*/', offset: $position + 2);
                $position = $commentEnd === false ? $length : $commentEnd + 2;
            } else {
                if (!str_contains(haystack: JsonUtils::WHITESPACE, needle: $character)) {
                    $minified .= $character;
                }
                $position++;
            }
        }

        return $minified;
    }

    /**
     * @return stdClass|array<array-key, mixed>
     *
     * @throws JsonException If the string is no valid JSON
     * @throws UnexpectedValueException If the JSON is no object and no array
     */
    public static function decodeJsonString(string $jsonString, bool $returnAssociativeArray): stdClass|array
    {
        $decoded = json_decode(
            json: $jsonString,
            associative: $returnAssociativeArray,
            flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
        );
        if (!$decoded instanceof stdClass && !is_array(value: $decoded)) {
            throw new UnexpectedValueException(
                message: 'The JSON must be an object or an array, ' . get_debug_type(value: $decoded) . ' found.',
            );
        }

        return $decoded;
    }

    /**
     * @return int The position after the closing quote (the end of the string if the string is not closed)
     */
    private static function findEndOfString(string $jsonString, int $startPosition): int
    {
        $length = strlen(string: $jsonString);
        $position = $startPosition + 1;
        while ($position < $length) {
            $position += strcspn(string: $jsonString, characters: '"\\', offset: $position);
            if ($position >= $length) {
                break;
            }
            if ($jsonString[$position] === '"') {
                return $position + 1;
            }
            $position += 2; // the backslash and the escaped character
        }

        return $length;
    }
}
