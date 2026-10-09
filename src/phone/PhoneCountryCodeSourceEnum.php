<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Where the country calling code of a parsed number comes from.
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
 *
 * @internal
 */
enum PhoneCountryCodeSourceEnum: int
{
    /** Derived from a number with a leading "+", e.g. the French number "+33 1 42 68 53 00". */
    case FROM_NUMBER_WITH_PLUS_SIGN = 0;
    /** Derived from a number with a leading IDD, e.g. "011 33 1 42 68 53 00" as it is dialled from the US. */
    case FROM_NUMBER_WITH_IDD = 1;
    /**
     * Not derived from the number itself, but from the default region given to the parser. This happens mostly for
     * numbers written in the national format (without country calling code).
     */
    case FROM_DEFAULT_COUNTRY = 3;
}
