<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   LGPL-2.1-only
 */

declare(strict_types=1);

/**
 * Derived work from the SMTP class of PHPMailer, reduced to the code needed by this Framework.
 * For the original full library, please see:
 *
 * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 * @author    Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author    Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author    Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author    Brent R. Matzelle (original founder)
 * @author    Actra AG (for derived, reduced code)  - www.actra.ch
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @copyright 2022 Actra AG
 * @license   https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html GNU Lesser General Public License, version 2.1
 *            only (LGPL-2.1-only, as PHPMailer), see the file LICENSE in src/mailer/. Changed by Actra AG: reduced,
 *            split into classes and adapted to the yuf coding standard; see the Git history of yuf for details.
 * @note      This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

namespace actra\yuf\mailer;

use Override;

/**
 * The connection to an SMTP server over a PHP stream socket.
 *
 * @internal
 */
final class StreamSmtpTransport implements SmtpTransport
{
    private const int LINE_BUFFER_LENGTH = 512; // https://www.rfc-editor.org/rfc/rfc5321#section-4.5.3.1.5

    /** @var resource|null */
    private $stream = null;

    /**
     * The TLS options of the connection: the certificate chain and the host name are verified.
     *
     * @return array{ssl: array<string, bool|string>}
     */
    public static function createContextOptions(string $hostName): array
    {
        return [
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'peer_name' => $hostName,
                'SNI_enabled' => true,
                'SNI_server_name' => $hostName,
            ],
        ];
    }

    #[Override]
    public function open(string $hostName, int $port, int $timeoutSeconds): void
    {
        $this->close();
        $address = str_contains(haystack: $hostName, needle: ':') && !str_starts_with(haystack: $hostName, needle: '[')
            ? '[' . $hostName . ']'
            : $hostName;
        $errorCode = 0;
        $errorMessage = '';
        // A failed connection is reported as exception, not as warning
        set_error_handler(callback: static fn(): bool => true);
        try {
            $stream = stream_socket_client(
                address: 'tcp://' . $address . ':' . $port,
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
            throw new MailerException(message: 'Socket connection error: ' . $hostName . ' (' . $errorCode . ')');
        }
        $this->stream = $stream;
    }

    #[Override]
    public function isOpen(): bool
    {
        return $this->stream !== null && !stream_get_meta_data(stream: $this->stream)['eof'];
    }

    #[Override]
    public function readLine(int $timeoutSeconds): ?string
    {
        if ($this->stream === null) {
            return null;
        }
        stream_set_timeout(stream: $this->stream, seconds: $timeoutSeconds);
        $line = fgets(stream: $this->stream, length: StreamSmtpTransport::LINE_BUFFER_LENGTH);
        if ($line !== false) {
            return $line;
        }
        if (stream_get_meta_data(stream: $this->stream)['timed_out']) {
            throw new MailerException(
                message: 'The SMTP server did not answer within ' . $timeoutSeconds . ' seconds.',
            );
        }

        return null;
    }

    #[Override]
    public function writeLine(string $line): void
    {
        if ($this->stream === null) {
            throw new MailerException(message: 'The connection to the SMTP server is closed.');
        }
        $data = $line . MailerConstants::CRLF;
        $length = strlen(string: $data);
        for ($written = 0; $written < $length; $written += $bytes) {
            $bytes = fwrite(stream: $this->stream, data: substr(string: $data, offset: $written));
            if ($bytes === false || $bytes === 0) {
                throw new MailerException(message: 'Could not send data to the SMTP server.');
            }
        }
    }

    #[Override]
    public function enableTls(): bool
    {
        if ($this->stream === null) {
            return false;
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
}
