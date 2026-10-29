<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailbox;

use actra\yuf\mailbox\ImapException;
use actra\yuf\mailbox\ImapTransport;
use Override;

/**
 * An IMAP server that answers from a script: the answers are read in order as one byte stream (lines and literals),
 * what the mailbox sends is recorded together with the other events of the connection.
 */
final class FakeImapTransport implements ImapTransport
{
    /** The bytes of the answers that are not read yet */
    private string $input;

    /**
     * @var list<string> `open host:port` (`open tls host:port` with implicit TLS), `write <data without CRLF>`, `tls`,
     *                   `close` in the order they happened
     */
    public private(set) array $events = [];

    private bool $isOpen = false;

    /**
     * @param list<string> $answers the answers with their line breaks, e.g. `"* OK ready\r\n"`
     */
    public function __construct(
        array $answers,
        private readonly bool $tlsSucceeds = true,
        private readonly bool $openFails = false,
    ) {
        $this->input = implode(separator: '', array: $answers);
    }

    #[Override]
    public function open(string $hostName, int $port, int $timeoutSeconds, bool $implicitTls): void
    {
        $this->events[] = ($implicitTls ? 'open tls ' : 'open ') . $hostName . ':' . $port;
        if ($this->openFails) {
            throw new ImapException(message: 'Cannot connect to the IMAP server ' . $hostName . '.');
        }
        $this->isOpen = true;
    }

    #[Override]
    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    #[Override]
    public function readLine(): ?string
    {
        if ($this->input === '') {
            return null;
        }
        $position = strpos(haystack: $this->input, needle: "\n");
        $length = $position === false ? strlen(string: $this->input) : $position + 1;
        $line = substr(string: $this->input, offset: 0, length: $length);
        $this->input = substr(string: $this->input, offset: $length);

        return $line;
    }

    #[Override]
    public function readBytes(int $length): ?string
    {
        if (strlen(string: $this->input) < $length) {
            return null;
        }
        $bytes = substr(string: $this->input, offset: 0, length: $length);
        $this->input = substr(string: $this->input, offset: $length);

        return $bytes;
    }

    #[Override]
    public function write(string $data): void
    {
        $this->events[] = 'write ' . preg_replace(pattern: '/\r\n$/D', replacement: '', subject: $data);
    }

    #[Override]
    public function enableTls(): bool
    {
        $this->events[] = 'tls';

        return $this->tlsSucceeds;
    }

    #[Override]
    public function close(): void
    {
        $this->events[] = 'close';
        $this->isOpen = false;
    }

    /**
     * @return list<string> the commands the mailbox sent, without the other events
     */
    public function writtenData(): array
    {
        $data = [];
        foreach ($this->events as $event) {
            if (str_starts_with(haystack: $event, needle: 'write ')) {
                $data[] = substr(string: $event, offset: 6);
            }
        }

        return $data;
    }
}
