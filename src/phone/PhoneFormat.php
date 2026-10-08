<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * A number format of a region: a pattern that splits a national number into groups and how to join the groups.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 *
 * @internal
 */
final readonly class PhoneFormat
{
    /**
     * @param list<string> $leadingDigitsPatterns the last one is the most detailed
     * @param string $nationalPrefixFormattingRule how the national format places the national prefix around the first
     *      group (`0$1`, `($1)`), empty if the format has no rule
     */
    public function __construct(
        public string $pattern,
        public string $format,
        public array $leadingDigitsPatterns,
        public string $nationalPrefixFormattingRule,
    ) {}
}
