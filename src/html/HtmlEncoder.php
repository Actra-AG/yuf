<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\html;

use stdClass;

class HtmlEncoder
{
    public static function encodeArray(array $array, bool $keepQuotes): array
    {
        foreach ($array as $key => $val) {
            if (is_array(value: $val)) {
                $array[$key] = HtmlEncoder::encodeArray(array: $val, keepQuotes: $keepQuotes);
                continue;
            }
            if (is_object(value: $val)) {
                $array[$key] = HtmlEncoder::encodeObject(object: $val, keepQuotes: $keepQuotes);
                continue;
            }
            if ($keepQuotes) {
                $array[$key] = HtmlEncoder::encodeKeepQuotes(value: $val);
                continue;
            }
            $array[$key] = HtmlEncoder::encode(value: $val);
        }

        return $array;
    }

    public static function encodeObject(stdClass $object, bool $keepQuotes): stdClass
    {
        foreach (get_object_vars(object: $object) as $key => $val) {
            if (is_array(value: $val)) {
                $object->{$key} = HtmlEncoder::encodeArray(array: $val, keepQuotes: $keepQuotes);
                continue;
            }
            if (is_object(value: $val)) {
                $object->{$key} = HtmlEncoder::encodeObject(object: $val, keepQuotes: $keepQuotes);
                continue;
            }
            if ($keepQuotes) {
                $object->{$key} = HtmlEncoder::encodeKeepQuotes(value: $val);
                continue;
            }
            $object->{$key} = HtmlEncoder::encode(value: $val);
        }

        return $object;
    }

    public static function encodeKeepQuotes(string|float|int|bool|null $value): string
    {
        return $value === null ? '' : htmlspecialchars(
            string: (string) $value,
            flags: ENT_NOQUOTES,
        );
    }

    public static function encode(string|float|int|bool|null $value): string
    {
        return $value === null ? '' : htmlspecialchars(
            string: (string) $value,
            flags: ENT_QUOTES,
        );
    }
}
