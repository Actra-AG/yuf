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
use RuntimeException;

/**
 * Normalizes input (trimming, domain notation, number formats); never validates and never repairs malicious input.
 * Stays a static class: every method is a pure function of its argument without state.
 */
final readonly class Sanitizer
{
    public static function domain(string $input): string
    {
        return DomainSanitizer::sanitize(input: $input);
    }

    public static function trimmedString(string|float|int|bool|null $input): string
    {
        if ($input === null) {
            return '';
        }

        return trim(string: (string) $input);
    }

    /**
     * @throws RuntimeException if the input is no integer or out of range
     */
    public static function integer(float|int|string $input): int
    {
        return IntegerSanitizer::sanitize(input: $input);
    }

    /**
     * @throws RuntimeException if the input is no number
     */
    public static function float(float|int|string $input): float
    {
        return FloatSanitizer::sanitize(input: $input);
    }
}
