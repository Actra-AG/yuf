<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * How `SmtpMailer` encrypts the connection to the SMTP server. The certificate of the server is always verified
 * (certificate chain and host name, TLS 1.2 or newer); there is never a fallback to plain text.
 */
enum SmtpEncryptionEnum
{
    /** Plain connection, encrypted with `STARTTLS` before the credentials are sent (port 587) */
    case STARTTLS;

    /** Encrypted from the first byte (SMTPS, port 465), no `STARTTLS` */
    case IMPLICIT_TLS;

    /** No encryption: only for a server on the same host (loopback), `SmtpMailer` refuses AUTH to any other host */
    case NONE;

    public function getDefaultPort(): int
    {
        return match ($this) {
            SmtpEncryptionEnum::STARTTLS => 587,
            SmtpEncryptionEnum::IMPLICIT_TLS => 465,
            SmtpEncryptionEnum::NONE => 25,
        };
    }
}
