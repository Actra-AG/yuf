<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use InvalidArgumentException;
use Override;
use SensitiveParameter;

/**
 * Sends with an SMTP server (EHLO, STARTTLS, AUTH LOGIN, MAIL FROM, RCPT TO, DATA).
 *
 * With `$useTls` the connection is encrypted with STARTTLS before the credentials are sent; the certificate of the
 * server must be valid for `$hostName`, TLS 1.2 or newer is required, and a server without STARTTLS or a failed
 * handshake aborts the delivery (there is no fallback to plain text). Without `$useTls` everything, also the
 * password, goes over the network unencrypted: only for a server on the same host.
 */
final class SmtpMailer extends AbstractMailer
{
    private const int CONNECT_TIMEOUT = 30;
    private const int COMMAND_TIMEOUT = 10;
    // https://www.rfc-editor.org/rfc/rfc2821#section-4.5.3.2
    private const int GREETING_TIMEOUT = 300;
    private const int SENDER_AND_RECIPIENT_TIMEOUT = 300;
    private const int DATA_START_TIMEOUT = 120;
    private const int DATA_END_TIMEOUT = 600;
    private const int QUIT_TIMEOUT = 60;
    private const string HIDDEN_COMMAND = '(hidden)';

    /** @var string The last answer of the server (all lines of it) */
    public private(set) string $lastReply = '';

    /** @var list<string> The lines of the last delivery: the answers and the commands, without credentials */
    public private(set) array $log = [];

    public function __construct(
        string $serverAddress,
        private readonly string $hostName,
        private readonly string $smtpUserName,
        #[SensitiveParameter]
        private readonly string $smtpPassword,
        private readonly int $port = 587,
        private readonly bool $useTls = true,
        private readonly SmtpTransport $transport = new StreamSmtpTransport(),
        Clock $clock = new SystemClock(),
        MimeIdGenerator $mimeIdGenerator = new RandomMimeIdGenerator(),
        ServerNameResolver $serverNameResolver = new ReverseDnsServerNameResolver(),
    ) {
        if (preg_match(pattern: '/^[A-Za-z0-9._:\[\]-]+$/D', subject: $hostName) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid SMTP host name: use a host name or an IP address.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(message: 'Invalid SMTP port ' . $port . ': use 1 to 65535.');
        }
        parent::__construct(
            serverAddress: $serverAddress,
            clock: $clock,
            mimeIdGenerator: $mimeIdGenerator,
            serverNameResolver: $serverNameResolver,
        );
    }

    #[Override]
    public function headerHasTo(): bool
    {
        return true;
    }

    #[Override]
    public function headerHasSubject(): bool
    {
        return true;
    }

    #[Override]
    public function headerHasBcc(): bool
    {
        return false;
    }

    #[Override]
    public function getMaxLineLength(): int
    {
        return MailerConstants::MAX_LINE_LENGTH;
    }

    #[Override]
    public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $this->lastReply = '';
        $this->log = [];
        $this->transport->open(
            hostName: $this->hostName,
            port: $this->port,
            timeoutSeconds: SmtpMailer::CONNECT_TIMEOUT,
        );
        try {
            $this->deliver(abstractMail: $abstractMail, mailMimeHeader: $mailMimeHeader, mailMimeBody: $mailMimeBody);
        } finally {
            $this->transport->close();
        }
    }

    private function deliver(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $this->readReply(
            expectedCode: 220,
            timeoutSeconds: SmtpMailer::GREETING_TIMEOUT,
            description: 'the connection',
        );
        $this->ehlo();
        if ($this->useTls) {
            $this->startTls();
            $this->ehlo();
        }
        if ($this->smtpUserName !== '') {
            $this->authenticate();
        }
        $this->sendCommand(
            command: 'MAIL FROM: <' . $abstractMail->sender->getPunyEncodedEmail() . '>',
            expectedCode: 250,
            timeoutSeconds: SmtpMailer::SENDER_AND_RECIPIENT_TIMEOUT,
            description: 'MAIL FROM',
        );
        $recipientKinds = [
            MailerAddressKindEnum::KIND_TO,
            MailerAddressKindEnum::KIND_CC,
            MailerAddressKindEnum::KIND_BCC,
        ];
        foreach ($recipientKinds as $kind) {
            foreach ($abstractMail->mailerAddressCollection->list(mailerAddressKindEnum: $kind) as $recipient) {
                $this->sendCommand(
                    command: 'RCPT TO: <' . $recipient->getPunyEncodedEmail() . '>',
                    expectedCode: 250,
                    timeoutSeconds: SmtpMailer::SENDER_AND_RECIPIENT_TIMEOUT,
                    description: 'RCPT TO',
                );
            }
        }
        $this->sendData(data: $mailMimeHeader->getMimeHeader() . "\r\n\r\n" . $mailMimeBody->getMimeBody());
        $this->sendCommand(
            command: 'QUIT',
            expectedCode: 221,
            timeoutSeconds: SmtpMailer::QUIT_TIMEOUT,
            description: 'QUIT',
        );
    }

    private function ehlo(): void
    {
        $this->sendCommand(
            command: 'EHLO ' . $this->getServerName(),
            expectedCode: 250,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'EHLO',
        );
    }

    private function startTls(): void
    {
        $this->sendCommand(
            command: 'STARTTLS',
            expectedCode: 220,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'STARTTLS',
        );
        if (!$this->transport->enableTls()) {
            throw new MailerException(
                message: 'The TLS connection to the SMTP server could not be established: check the certificate of '
                    . $this->hostName . '. Nothing was sent.',
            );
        }
    }

    private function authenticate(): void
    {
        $this->sendCommand(
            command: 'AUTH LOGIN',
            expectedCode: 334,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'AUTH LOGIN',
        );
        $this->sendCommand(
            command: base64_encode(string: $this->smtpUserName),
            expectedCode: 334,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'the user name',
            isCredential: true,
        );
        $this->sendCommand(
            command: base64_encode(string: $this->smtpPassword),
            expectedCode: 235,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'the password',
            isCredential: true,
        );
    }

    /**
     * Sends the `DATA` command and the message (RFC 5321 section 4.1.1.4): header and body are separated by an empty
     * line.
     */
    private function sendData(string $data): void
    {
        $this->sendCommand(
            command: 'DATA',
            expectedCode: 354,
            timeoutSeconds: SmtpMailer::DATA_START_TIMEOUT,
            description: 'DATA',
        );
        foreach (SmtpDataFormatter::toLines(data: $data) as $line) {
            $this->writeLine(line: $line, isCredential: false);
        }
        $this->sendCommand(
            command: '.',
            expectedCode: 250,
            timeoutSeconds: SmtpMailer::DATA_END_TIMEOUT,
            description: 'the end of the message',
        );
    }

    /**
     * @param bool $isCredential the command carries the user name or the password: it is not logged
     */
    private function sendCommand(
        string $command,
        int $expectedCode,
        int $timeoutSeconds,
        string $description,
        bool $isCredential = false,
    ): void {
        if (!$this->transport->isOpen()) {
            throw new MailerException(message: 'Tried to send ' . $description . ' without being connected.');
        }
        // A line break would end the command and start another one (SMTP command injection)
        if (strpbrk(string: $command, characters: MailerConstants::CRLF) !== false) {
            throw new MailerException(message: 'The command ' . $description . ' contains a line break.');
        }
        $this->writeLine(line: $command, isCredential: $isCredential);
        $this->readReply(expectedCode: $expectedCode, timeoutSeconds: $timeoutSeconds, description: $description);
    }

    private function writeLine(string $line, bool $isCredential): void
    {
        $this->log[] = $isCredential ? SmtpMailer::HIDDEN_COMMAND : $line;
        $this->transport->writeLine(line: $line);
    }

    private function readReply(int $expectedCode, int $timeoutSeconds, string $description): void
    {
        $this->lastReply = $this->readLines(timeoutSeconds: $timeoutSeconds);
        if ($this->lastReply === '') {
            throw new MailerException(
                message: 'The SMTP server closed the connection (waiting for the answer to ' . $description . ').',
            );
        }
        $code = (int) substr(string: $this->lastReply, offset: 0, length: 3);
        if ($code !== $expectedCode) {
            throw new MailerException(
                message: 'Unexpected answer of the SMTP server to ' . $description . ': code ' . $code
                    . ' instead of ' . $expectedCode . '.',
            );
        }
    }

    /**
     * Reads the lines of one answer: the last line has a space (or nothing) after the code, the lines before have a
     * hyphen.
     */
    private function readLines(int $timeoutSeconds): string
    {
        $data = '';
        while (($line = $this->transport->readLine(timeoutSeconds: $timeoutSeconds)) !== null) {
            $this->log[] = $line;
            $data .= $line;
            // An answer of only 3 characters is not valid, but RFC 5321 section 4.2 says it must be handled
            $separator = substr(string: $line, offset: 3, length: 1);
            if (in_array(needle: $separator, haystack: ['', ' ', "\r", "\n"], strict: true)) {
                break;
            }
        }

        return $data;
    }
}
