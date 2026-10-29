<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * The capabilities of an IMAP server that `StreamImapMailbox` uses (RFC 9051 section 7.2.2).
 */
enum ImapCapabilityEnum: string
{
    case STARTTLS = 'STARTTLS';
    case LOGINDISABLED = 'LOGINDISABLED';
    case AUTH_PLAIN = 'AUTH=PLAIN';

    /** Initial response with `AUTHENTICATE` (RFC 4959) */
    case SASL_IR = 'SASL-IR';

    /** `UID MOVE` (RFC 6851) */
    case MOVE = 'MOVE';

    /** `UID EXPUNGE` (RFC 4315) */
    case UIDPLUS = 'UIDPLUS';
}
