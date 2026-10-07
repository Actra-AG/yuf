<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\common;

use Exception;
use stdClass;

class JsonUtils
{
    public static function convertToJsonString(mixed $valueToConvert): string
    {
        return json_encode(
            value: $valueToConvert,
            flags: JSON_UNESCAPED_UNICODE | JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
        );
    }

    public static function decodeFile(
        string $filePath,
        bool $isMinified,
        bool $returnAssociativeArray = false,
    ): stdClass|array {
        if (file_exists(filename: $filePath) === false) {
            throw new Exception(message: 'JSON-File does not exist: ' . $filePath);
        }
        $jsonString = file_get_contents(filename: $filePath);

        if ($isMinified === false && $jsonString !== '{}') {
            $jsonString = JsonUtils::minify(jsonString: $jsonString);
        }

        return JsonUtils::decodeJsonString(jsonString: $jsonString, returnAssociativeArray: $returnAssociativeArray);
    }

    public static function minify(string $jsonString): string
    {
        $tokenizer = "/\"|(\/\*)|(\*\/)|(\/\/)|\n|\r/";
        $inString = false;
        $inMultilineComment = false;
        $inSinglelineComment = false;
        $tmp = null;
        $tmp2 = null;
        $newStr = [];
        $from = 0;
        $rc = null;
        $lastIndex = 0;

        while (preg_match($tokenizer, $jsonString, $tmp, PREG_OFFSET_CAPTURE, $lastIndex)) {
            $tmp = $tmp[0];
            $lastIndex = $tmp[1] + strlen($tmp[0]);
            $lc = substr($jsonString, 0, $lastIndex - strlen($tmp[0]));
            $rc = substr($jsonString, $lastIndex);

            if (!$inMultilineComment && !$inSinglelineComment) {
                $tmp2 = substr($lc, $from);
                if (!$inString) {
                    $tmp2 = preg_replace(pattern: "/(\n|\r|\s)*/", replacement: '', subject: $tmp2);
                }

                $newStr[] = $tmp2;
            }

            $from = $lastIndex;

            if ($tmp[0] === '"' && !$inMultilineComment && !$inSinglelineComment) {
                preg_match('/(\\\\)*$/', $lc, $tmp2);

                if (!$inString || !$tmp2 || (strlen(
                    $tmp2[0],
                ) % 2) === 0) { // start of string with ", or unescaped " character found to end string
                    $inString = !$inString;
                }

                $from--; // include " character in next catch
                $rc = substr($jsonString, $from);
            } elseif ($tmp[0] === '/*' && !$inString && !$inMultilineComment && !$inSinglelineComment) {
                $inMultilineComment = true;
            } elseif ($tmp[0] === '*/' && !$inString && $inMultilineComment && !$inSinglelineComment) {
                $inMultilineComment = false;
            } elseif ($tmp[0] === '//' && !$inString && !$inMultilineComment && !$inSinglelineComment) {
                $inSinglelineComment = true;
            } elseif (($tmp[0] === "\n" || $tmp[0] === "\r") && !$inString && !$inMultilineComment && $inSinglelineComment) {
                $inSinglelineComment = false;
            } elseif (!$inMultilineComment && !$inSinglelineComment && !(preg_match("/\n|\r|\s/", $tmp[0]))) {
                $newStr[] = $tmp[0];
            }
        }
        $newStr[] = $rc;

        return implode(separator: StringUtils::IMPLODE_DEFAULT_SEPARATOR, array: $newStr);
    }

    public static function decodeJsonString(string $jsonString, bool $returnAssociativeArray): stdClass|array
    {
        return json_decode(
            json: $jsonString,
            associative: $returnAssociativeArray,
            flags: JSON_BIGINT_AS_STRING | JSON_THROW_ON_ERROR,
        );
    }
}
