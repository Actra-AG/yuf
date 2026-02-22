<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\datacheck;

use actra\yuf\datacheck\sanitizerTypes\DomainSanitizer;
use actra\yuf\datacheck\sanitizerTypes\FloatSanitizer;
use actra\yuf\datacheck\sanitizerTypes\IntegerSanitizer;

/**
 * Class "Sanitizer" is a "helper class"
 */
class Sanitizer
{
    public static function domain($input): string
    {
        return DomainSanitizer::sanitize(input: $input);
    }

    public static function trimmedString(null|string|float|int|bool $input): string
    {
        if (is_null(value: $input)) {
            return '';
        }

        return trim(string: $input);
    }

    public static function integer($input): int
    {
        return IntegerSanitizer::sanitize(input: $input);
    }

    public static function float($input): float
    {
        return FloatSanitizer::sanitize(input: $input);
    }
}