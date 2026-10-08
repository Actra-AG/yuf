<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * The SMTP authentication methods of `SmtpMailer`. The value is the name in the `AUTH` line of the server (RFC 4954).
 * There is no `CRAM-MD5`.
 */
enum SmtpAuthMethodEnum: string
{
    /** User name and password in two steps */
    case LOGIN = 'LOGIN';

    /** User name and password in one step (RFC 4616) */
    case PLAIN = 'PLAIN';

    /** User name (the mailbox) and an OAuth 2.0 access token (Microsoft 365, Gmail) */
    case XOAUTH2 = 'XOAUTH2';
}
