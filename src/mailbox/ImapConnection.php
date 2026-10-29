<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

/**
 * The IMAP protocol over an `ImapTransport` (RFC 9051): tagged commands, synchronizing literals in both directions,
 * the status of the tagged response. Values in `$hiddenValues` (password, credentials) never appear in exceptions.
 *
 * @internal
 */
final class ImapConnection
{
    private const string CRLF = "\r\n";

    /** Larger literals (e.g. a message) are refused, so a server cannot exhaust the memory */
    private const int MAX_LITERAL_BYTES = 67_108_864;

    private int $tagNumber = 0;

    /** @var list<string> */
    private array $hiddenValues = [];

    public function __construct(private readonly ImapTransport $transport) {}

    public function hide(string $value): void
    {
        $this->hiddenValues[] = $value;
    }

    /**
     * @return ImapResponseLine the greeting, `* OK ...`
     *
     * @throws ImapException if the server refuses the connection (`* BYE`) or is already authenticated (`* PREAUTH`,
     *                       the connection would never be encrypted with STARTTLS)
     */
    public function readGreeting(): ImapResponseLine
    {
        $greeting = $this->readResponseLine();
        if (preg_match(pattern: '/^\* OK\b/i', subject: $greeting->getText()) !== 1) {
            throw new ImapException(
                message: 'The IMAP server did not greet with OK.',
                serverResponse: $this->hideValues(text: $greeting->getText()),
            );
        }

        return $greeting;
    }

    /**
     * Sends the command and reads the answer up to the tagged status line.
     *
     * @param string                   $name      the command, e.g. `SELECT` or `UID FETCH` (used in exceptions)
     * @param list<string|ImapLiteral> $arguments formatted arguments (see `ImapArgument`) and literals
     *
     * @throws ImapException if the server answers `NO` or `BAD` or closes the connection
     */
    public function command(string $name, array $arguments = []): ImapResponse
    {
        $tag = $this->nextTag();
        $line = $tag . ' ' . $name;
        foreach ($arguments as $argument) {
            if (!$argument instanceof ImapLiteral) {
                $line .= ' ' . $argument;

                continue;
            }
            $this->transport->write(data: $line . ' {' . strlen(string: $argument->bytes) . '}' . ImapConnection::CRLF);
            $this->awaitContinuation(tag: $tag, name: $name);
            $line = $argument->bytes;
        }
        $this->transport->write(data: $line . ImapConnection::CRLF);

        return $this->readResponse(tag: $tag, name: $name);
    }

    /**
     * `AUTHENTICATE PLAIN` (RFC 4616), with the initial response in the command if the server supports SASL-IR.
     *
     * @throws ImapException if the server rejects the credentials
     */
    public function authenticatePlain(string $credentials, bool $hasInitialResponse): ImapResponse
    {
        $encoded = base64_encode(string: $credentials);
        $this->hide(value: $encoded);
        $tag = $this->nextTag();
        $name = 'AUTHENTICATE';
        if ($hasInitialResponse) {
            $this->transport->write(data: $tag . ' AUTHENTICATE PLAIN ' . $encoded . ImapConnection::CRLF);

            return $this->readResponse(tag: $tag, name: $name);
        }
        $this->transport->write(data: $tag . ' AUTHENTICATE PLAIN' . ImapConnection::CRLF);
        $this->awaitContinuation(tag: $tag, name: $name);
        $this->transport->write(data: $encoded . ImapConnection::CRLF);

        return $this->readResponse(tag: $tag, name: $name);
    }

    /**
     * Sends `LOGOUT` and reads up to the tagged answer or until the server closes the connection.
     */
    public function logout(): void
    {
        $tag = $this->nextTag();
        $this->transport->write(data: $tag . ' LOGOUT' . ImapConnection::CRLF);
        while (true) {
            $line = $this->transport->readLine();
            if ($line === null || str_starts_with(haystack: $line, needle: $tag . ' ')) {
                return;
            }
        }
    }

    private function nextTag(): string
    {
        $this->tagNumber++;

        return 'A' . str_pad(string: (string) $this->tagNumber, length: 4, pad_string: '0', pad_type: STR_PAD_LEFT);
    }

    private function awaitContinuation(string $tag, string $name): void
    {
        while (true) {
            $line = $this->readResponseLine();
            $text = $line->getText();
            if (str_starts_with(haystack: $text, needle: '+')) {
                return;
            }
            if (str_starts_with(haystack: $text, needle: $tag . ' ')) {
                throw new ImapException(
                    message: 'The IMAP server rejected ' . $name . '.',
                    serverResponse: $this->hideValues(text: $text),
                );
            }
        }
    }

    private function readResponse(string $tag, string $name): ImapResponse
    {
        $untaggedLines = [];
        while (true) {
            $line = $this->readResponseLine();
            $text = $line->getText();
            if (!str_starts_with(haystack: $text, needle: $tag . ' ')) {
                $untaggedLines[] = $line;

                continue;
            }
            $hasStatus = preg_match(pattern: '/^\S+ ([A-Za-z]+)/', subject: $text, matches: $matches) === 1;
            $status = ImapResponseStatusEnum::tryFrom(value: $hasStatus ? strtoupper(string: $matches[1]) : '');
            if ($status !== ImapResponseStatusEnum::OK) {
                throw new ImapException(
                    message: 'The IMAP server rejected ' . $name . '.',
                    serverResponse: $this->hideValues(text: $text),
                );
            }

            return new ImapResponse(untaggedLines: $untaggedLines, status: $status, statusLine: $text);
        }
    }

    /**
     * Reads one response with all its literals.
     */
    private function readResponseLine(): ImapResponseLine
    {
        $segments = [];
        $literals = [];
        while (true) {
            $line = $this->transport->readLine();
            if ($line === null) {
                throw new ImapException(
                    message: 'The IMAP server closed the connection.',
                    serverResponse: $this->hideValues(text: implode(separator: ' ', array: $segments)),
                );
            }
            $segment = rtrim(string: $line, characters: "\r\n");
            $segments[] = $segment;
            if (preg_match(pattern: '/\{(\d+)\+?\}$/D', subject: $segment, matches: $matches) !== 1) {
                return new ImapResponseLine(segments: $segments, literals: $literals);
            }
            $literals[] = $this->readLiteral(length: (int) $matches[1]);
        }
    }

    private function readLiteral(int $length): string
    {
        if ($length > ImapConnection::MAX_LITERAL_BYTES) {
            throw new ImapException(
                message: 'The IMAP server sent ' . $length . ' bytes, more than the maximum of '
                . ImapConnection::MAX_LITERAL_BYTES . '.',
            );
        }
        $bytes = $this->transport->readBytes(length: $length);
        if ($bytes === null) {
            throw new ImapException(message: 'The IMAP server closed the connection.');
        }

        return $bytes;
    }

    private function hideValues(string $text): string
    {
        foreach ($this->hiddenValues as $value) {
            if ($value !== '') {
                $text = str_replace(search: $value, replace: '(hidden)', subject: $text);
            }
        }

        return $text;
    }
}
