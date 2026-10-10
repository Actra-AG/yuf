<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam;

/**
 * The result of `SpamCheck::check()`: the first rule a submission breaks, or `OK`.
 */
enum SpamCheckResultEnum
{
    case OK;
    case REJECTED_EMAIL_ADDRESS;
    case HONEYPOT_FILLED;
    case TOO_FAST;
    case EXPIRED;
}
