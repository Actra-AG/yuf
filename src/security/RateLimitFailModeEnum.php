<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

/**
 * What a rate limit of a form or login does when it cannot count (the `RateLimiter` storage fails, the client IP
 * address is invalid): `CLOSED` blocks the request, `OPEN` lets it pass. The error is logged either way.
 */
enum RateLimitFailModeEnum
{
    /** Allow: for forms where a lost request does more harm than an uncounted one */
    case OPEN;
    /** Block: for anything that costs money, sends mails or calls a third party, and for logins */
    case CLOSED;
}
