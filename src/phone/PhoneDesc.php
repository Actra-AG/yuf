<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * The national number pattern and the possible lengths of a group of numbers of a region. An empty pattern matches no
 * number; empty possible lengths mean no restriction.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
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
