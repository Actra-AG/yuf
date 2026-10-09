<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   Apache-2.0
 */

declare(strict_types=1);

namespace actra\yuf\phone;

/**
 * The type of a phone number, as far as the metadata knows it. The values are the names of the groups in the
 * metadata.
 *
 * Adapted work based on libphonenumber-for-php (https://github.com/giggsey/libphonenumber-for-php), a port of
 * libphonenumber (https://github.com/google/libphonenumber), licensed under the Apache License, Version 2.0 (see
 * the files LICENSE and NOTICE in src/phone/). Changed by Actra AG, see NOTICE.
 */
enum PhoneNumberTypeEnum: string
{
    case FIXED_LINE = 'fixedLine';
    case MOBILE = 'mobile';
    /**
     * Only the result of `PhoneValidator::getNumberType()`: the number matches both the fixed line and the mobile
     * numbers of its region (e.g. in the US, where it cannot be told apart).
     */
    case FIXED_LINE_OR_MOBILE = 'fixedLineOrMobile';
    case TOLL_FREE = 'tollFree';
    case PREMIUM_RATE = 'premiumRate';
    case SHARED_COST = 'sharedCost';
    case VOIP = 'voip';
    case PERSONAL_NUMBER = 'personalNumber';
    case PAGER = 'pager';
    /** Universal access number: one number for a company that reaches its different offices. */
    case UAN = 'uan';
    case VOICEMAIL = 'voicemail';
}
