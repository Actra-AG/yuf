<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * The national number pattern and the possible lengths of a group of numbers of a region. An empty pattern matches no
 * number; empty possible lengths mean no restriction.
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
 *
 * @internal
 */
final readonly class PhoneDesc
{
    /**
     * @param list<int> $possibleLength
     * @param list<int> $possibleLengthLocalOnly
     */
    public function __construct(
        public string $nationalNumberPattern,
        public array $possibleLength,
        public array $possibleLengthLocalOnly,
    ) {}
}
