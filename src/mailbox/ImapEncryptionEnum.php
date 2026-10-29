<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * How `StreamImapMailbox` encrypts the connection to the IMAP server. The certificate of the server is always verified
 * (certificate chain and host name, TLS 1.2 or newer); there is no unencrypted connection. The values are the ones of
 * the environment file (`EnvironmentSettings::getEnum()`).
 */
enum ImapEncryptionEnum: string
{
    /** Encrypted from the first byte (IMAPS, port 993) */
    case IMPLICIT_TLS = 'implicit_tls';

    /** Plain connection, encrypted with `STARTTLS` before the credentials are sent (port 143) */
    case STARTTLS = 'starttls';

    public function getDefaultPort(): int
    {
        return match ($this) {
            ImapEncryptionEnum::IMPLICIT_TLS => 993,
            ImapEncryptionEnum::STARTTLS => 143,
        };
    }
}
