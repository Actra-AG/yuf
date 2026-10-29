<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * The result of a tagged response (RFC 9051 section 7.1).
 *
 * @internal
 */
enum ImapResponseStatusEnum: string
{
    case OK = 'OK';
    case NO = 'NO';
    case BAD = 'BAD';
}
