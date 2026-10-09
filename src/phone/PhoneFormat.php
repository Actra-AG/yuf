<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * A number format of a region: a pattern that splits a national number into groups and how to join the groups.
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
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
