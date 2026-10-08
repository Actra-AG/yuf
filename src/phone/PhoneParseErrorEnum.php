<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * Why a phone number could not be parsed. The value is the code of the `PhoneParseException`.
 *
 * Adapted work based on https://github.com/giggsey/libphonenumber-for-php , which was published
 * with "Apache License Version 2.0, January 2004" ( http://www.apache.org/licenses/ )
 */
enum PhoneParseErrorEnum: int
{
    /** The input is empty or only whitespace. */
    case EMPTY_STRING = 0;
    /** No default region and no usable country calling code, or an unknown country calling code. */
    case INVALID_COUNTRY_CODE = 1;
    /** The input does not look like a phone number. */
    case NOT_A_NUMBER = 2;
    /** After the international prefix, there are not enough digits left. */
    case TOO_SHORT_AFTER_IDD = 3;
    /** The national number has less than two digits. */
    case TOO_SHORT_NSN = 4;
    /** The input or the national number is too long. */
    case TOO_LONG = 5;
    /** The length of the number does not fit any possible number of its region. */
    case NOT_POSSIBLE = -1;
}
