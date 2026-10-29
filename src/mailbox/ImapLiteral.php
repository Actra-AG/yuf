<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * A command argument sent as synchronizing literal `{n}` (RFC 9051 section 4.3), for values that a quoted string
 * cannot hold (8-bit characters).
 *
 * @internal
 */
final readonly class ImapLiteral
{
    public function __construct(public string $bytes) {}
}
