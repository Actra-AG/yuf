<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use actra\yuf\mailer\StreamSmtpTransport;
use Override;

/**
 * The connection to an IMAP server over a PHP stream socket, with the same TLS options as the SMTP connection
 * (certificate chain and host name verified, TLS 1.2 or newer).
 */
final class StreamImapTransport implements ImapTransport
{
    private const int READ_CHUNK_LENGTH = 65_536;

    /** Longer lines (e.g. a `SEARCH` answer with millions of UIDs) are refused */
    private const int MAX_LINE_LENGTH = 8_388_608;

    /** @var resource|null */
    private $stream = null;

    private int $timeoutSeconds = 10;

    #[Override]
    public function open(string $hostName, int $port, int $timeoutSeconds, bool $implicitTls): void
    {
        $this->close();
        $this->timeoutSeconds = $timeoutSeconds;
        $errorCode = 0;
        $errorMessage = '';
        // A failed connection is reported as exception, not as warning
        set_error_handler(callback: static fn(): bool => true);
        try {
            $stream = stream_socket_client(
                address: StreamSmtpTransport::createAddress(
                    hostName: $hostName,
                    port: $port,
                    implicitTls: $implicitTls,
                ),
                error_code: $errorCode,
                error_message: $errorMessage,
                timeout: $timeoutSeconds,
                flags: STREAM_CLIENT_CONNECT,
                context: stream_context_create(options: StreamSmtpTransport::createContextOptions(hostName: $hostName)),
            );
        } finally {
            restore_error_handler();
        }
        if ($stream === false) {
            throw new ImapException(
                message: 'Cannot connect to the IMAP server ' . $hostName . ':' . $port . ' (' . $errorCode . ' '
                . $errorMessage . ').',
            );
        }
        $this->stream = $stream;
    }

    #[Override]
    public function isOpen(): bool
    {
        return $this->stream !== null && !stream_get_meta_data(stream: $this->stream)['eof'];
    }

    #[Override]
    public function readLine(): ?string
    {
        if ($this->stream === null) {
            return null;
        }
        stream_set_timeout(stream: $this->stream, seconds: $this->timeoutSeconds);
        $line = '';
        while (!str_ends_with(haystack: $line, needle: "\n")) {
            $chunk = fgets(stream: $this->stream, length: StreamImapTransport::READ_CHUNK_LENGTH);
            if ($chunk === false) {
                $this->throwIfTimedOut();

                return $line === '' ? null : $line;
            }
            $line .= $chunk;
            if (strlen(string: $line) > StreamImapTransport::MAX_LINE_LENGTH) {
                throw new ImapException(message: 'The IMAP server sent a line longer than the maximum.');
            }
        }

        return $line;
    }

    #[Override]
    public function readBytes(int $length): ?string
    {
        $stream = $this->stream;
        if ($stream === null) {
            return null;
        }
        stream_set_timeout(stream: $stream, seconds: $this->timeoutSeconds);
        $bytes = '';
        while (strlen(string: $bytes) < $length) {
            $chunk = fread(
                stream: $stream,
                length: max(1, min(StreamImapTransport::READ_CHUNK_LENGTH, $length - strlen(string: $bytes))),
            );
            if ($chunk === false || $chunk === '') {
                $this->throwIfTimedOut();
                if (feof(stream: $stream)) {
                    return null;
                }

                continue;
            }
            $bytes .= $chunk;
        }

        return $bytes;
    }

    #[Override]
    public function write(string $data): void
    {
        if ($this->stream === null) {
            throw new ImapException(message: 'The connection to the IMAP server is closed.');
        }
        $length = strlen(string: $data);
        for ($written = 0; $written < $length; $written += $bytes) {
            $bytes = fwrite(stream: $this->stream, data: substr(string: $data, offset: $written));
            if ($bytes === false || $bytes === 0) {
                throw new ImapException(message: 'Could not send data to the IMAP server.');
            }
        }
    }

    #[Override]
    public function enableTls(): bool
    {
        if ($this->stream === null) {
            return false;
        }
        // Bytes that arrived before the handshake are in the read buffer and would be read as if they were encrypted
        if (stream_get_meta_data(stream: $this->stream)['unread_bytes'] > 0) {
            throw new ImapException(message: 'The IMAP server sent data before the TLS handshake. Nothing was sent.');
        }
        // A failed handshake is reported by the return value, not as warning
        set_error_handler(callback: static fn(): bool => true);
        try {
            $result = stream_socket_enable_crypto(
                stream: $this->stream,
                enable: true,
                crypto_method: STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
            );
        } finally {
            restore_error_handler();
        }

        return $result === true;
    }

    #[Override]
    public function close(): void
    {
        if ($this->stream === null) {
            return;
        }
        fclose(stream: $this->stream);
        $this->stream = null;
    }

    public function __destruct()
    {
        $this->close();
    }

    private function throwIfTimedOut(): void
    {
        if ($this->stream !== null && stream_get_meta_data(stream: $this->stream)['timed_out']) {
            throw new ImapException(
                message: 'The IMAP server did not answer within ' . $this->timeoutSeconds . ' seconds.',
            );
        }
    }
}
