<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * The connection to an SMTP server, so that `SmtpMailer` can be tested without a network.
 */
interface SmtpTransport
{
    /**
     * @throws MailerException if the connection cannot be opened
     */
    public function open(string $hostName, int $port, int $timeoutSeconds): void;

    public function isOpen(): bool;

    /**
     * @return string|null one line of the server with its line break, `null` if the server closed the connection
     *
     * @throws MailerException if the server does not answer within the time
     */
    public function readLine(int $timeoutSeconds): ?string;

    /**
     * Sends the line and the line break `\r\n`.
     *
     * @throws MailerException if the line cannot be sent
     */
    public function writeLine(string $line): void;

    /**
     * Starts TLS on the open connection (after `STARTTLS`). The certificate of the server must be valid for the host
     * name of `open()`.
     *
     * @return bool whether the connection is encrypted now
     */
    public function enableTls(): bool;

    public function close(): void;
}
