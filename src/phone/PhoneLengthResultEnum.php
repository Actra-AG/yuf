<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Result of the length test of a national number against the possible lengths of a region.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 *
 * @internal
 */
enum PhoneLengthResultEnum: int
{
    /** The number length matches that of valid numbers for this region. */
    case IS_POSSIBLE = 0;
    /** The number is shorter than all valid numbers for this region. */
    case TOO_SHORT = 2;
    /** The number is longer than all valid numbers for this region. */
    case TOO_LONG = 3;
    /**
     * The number length matches that of local numbers for this region only (numbers that may be able to be dialled
     * within an area, but do not have all the information to be dialled from anywhere inside or outside the country).
     */
    case IS_POSSIBLE_LOCAL_ONLY = 4;
    /**
     * The number is longer than the shortest valid numbers for this region, shorter than the longest valid numbers
     * for this region, and does not itself have a number length that matches valid numbers for this region. Also
     * returned when there are no possible lengths at all.
     */
    case INVALID_LENGTH = 5;
}
