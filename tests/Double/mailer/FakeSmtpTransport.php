<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\SmtpTransport;
use LogicException;
use Override;

/**
 * An SMTP server that answers from a script: every reply is read in the order of the script, the commands that the
 * mailer sends are recorded together with the other events of the connection.
 */
final class FakeSmtpTransport implements SmtpTransport
{
    /** @var list<string> the lines of the replies that are not read yet */
    private array $lines = [];

    /** @var list<string> `open host:port`, `write <line>`, `tls`, `close` in the order they happened */
    public private(set) array $events = [];

    /** @var list<int> */
    public private(set) array $readTimeouts = [];

    private bool $isOpen = false;

    /**
     * @param list<string> $replies every reply with its line breaks, e.g. `"250-a\r\n250 b\r\n"`
     */
    public function __construct(
        array $replies,
        private readonly bool $tlsSucceeds = true,
        private readonly bool $openFails = false,
    ) {
        foreach ($replies as $reply) {
            $parts = preg_split(pattern: '/(?<=\n)/', subject: $reply, flags: PREG_SPLIT_NO_EMPTY);
            foreach ($parts === false ? [] : $parts as $line) {
                $this->lines[] = $line;
            }
        }
    }

    #[Override]
    public function open(string $hostName, int $port, int $timeoutSeconds): void
    {
        $this->events[] = 'open ' . $hostName . ':' . $port;
        if ($this->openFails) {
            throw new MailerException(message: 'Socket connection error: ' . $hostName);
        }
        $this->isOpen = true;
    }

    #[Override]
    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    #[Override]
    public function readLine(int $timeoutSeconds): ?string
    {
        $this->readTimeouts[] = $timeoutSeconds;

        return array_shift($this->lines);
    }

    #[Override]
    public function writeLine(string $line): void
    {
        $this->events[] = 'write ' . $line;
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

    public function lastEvent(): string
    {
        return array_last(array: $this->events) ?? throw new LogicException(message: 'Nothing happened yet.');
    }

    /**
     * @return list<string> the lines the mailer sent, without the other events
     */
    public function writtenLines(): array
    {
        $lines = [];
        foreach ($this->events as $event) {
            if (str_starts_with(haystack: $event, needle: 'write ')) {
                $lines[] = substr(string: $event, offset: 6);
            }
        }

        return $lines;
    }
}
