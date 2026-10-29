<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * The connection to an IMAP server, so that `StreamImapMailbox` can be tested without a network.
 */
interface ImapTransport
{
    /**
     * @param bool $implicitTls whether the connection is encrypted from the first byte (IMAPS): the handshake is part
     *                          of opening, the certificate must be valid for `$hostName`
     *
     * @throws ImapException if the connection cannot be opened (or the TLS handshake fails)
     */
    public function open(string $hostName, int $port, int $timeoutSeconds, bool $implicitTls): void;

    public function isOpen(): bool;

    /**
     * @return string|null one line of the server with its line break, `null` if the server closed the connection
     *
     * @throws ImapException if the server does not answer within the timeout of `open()` or the line is too long
     */
    public function readLine(): ?string;

    /**
     * @return string|null exactly `$length` bytes (a literal), `null` if the server closed the connection before
     *
     * @throws ImapException if the server does not answer within the timeout of `open()`
     */
    public function readBytes(int $length): ?string;

    /**
     * Sends the data as it is (the caller adds the line break `\r\n`).
     *
     * @throws ImapException if the data cannot be sent
     */
    public function write(string $data): void;

    /**
     * Starts TLS on the open connection (after `STARTTLS`). The certificate of the server must be valid for the host
     * name of `open()`.
     *
     * @return bool whether the connection is encrypted now
     *
     * @throws ImapException if the server sent data before the handshake (it would be read as if it was encrypted)
     */
    public function enableTls(): bool;

    public function close(): void;
}
