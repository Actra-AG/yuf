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

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\common\FileCache;
use InvalidArgumentException;
use Override;
use SensitiveParameter;

/**
 * Sends with an SMTP server (EHLO, STARTTLS, AUTH, MAIL FROM, RCPT TO, DATA).
 *
 * Authentication (only with a user name): `$authMethod` fixes the method, `null` chooses it from the `AUTH` line of the
 * answer to `EHLO` (after STARTTLS): with an `$oAuthTokenProvider` `XOAUTH2` (user name = mailbox, the password is
 * not used), otherwise `PLAIN` before `LOGIN`. A server that announces no methods at all gets `LOGIN` (or `XOAUTH2`
 * with a token provider). A fixed method that the server does not announce aborts the delivery.
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

    /**
     * @param ?FileCache $serverNameCache Keeps the host name of the server (reverse DNS, used in `EHLO` and the
     *                                    message IDs) for a day: `$core->fileCache`; `null` looks it up per mailer
     * @param ?ServerNameResolver $serverNameResolver Default: reverse DNS with the `serverNameCache`
     */
    public function __construct(
        string $serverAddress,
        private readonly string $hostName,
        private readonly string $smtpUserName,
        #[SensitiveParameter]
        private readonly string $smtpPassword,
        ?FileCache $serverNameCache,
        private readonly int $port = 587,
        private readonly bool $useTls = true,
        private readonly SmtpTransport $transport = new StreamSmtpTransport(),
        Clock $clock = new SystemClock(),
        MimeIdGenerator $mimeIdGenerator = new RandomMimeIdGenerator(),
        ?ServerNameResolver $serverNameResolver = null,
        private readonly ?SmtpAuthMethodEnum $authMethod = null,
        private readonly ?OAuthTokenProvider $oAuthTokenProvider = null,
    ) {
        if (preg_match(pattern: '/^[A-Za-z0-9._:\[\]-]+$/D', subject: $hostName) !== 1) {
            throw new InvalidArgumentException(message: 'Invalid SMTP host name: use a host name or an IP address.');
        }
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException(message: 'Invalid SMTP port ' . $port . ': use 1 to 65535.');
        }
        if ($authMethod === SmtpAuthMethodEnum::XOAUTH2 && $oAuthTokenProvider === null) {
            throw new InvalidArgumentException(
                message: 'The authentication method XOAUTH2 needs an OAuthTokenProvider.',
            );
        }
        if ($oAuthTokenProvider !== null && $authMethod !== null && $authMethod !== SmtpAuthMethodEnum::XOAUTH2) {
            throw new InvalidArgumentException(
                message: 'An OAuthTokenProvider can only be used with the authentication method XOAUTH2.',
            );
        }
        parent::__construct(
            serverAddress: $serverAddress,
            clock: $clock,
            mimeIdGenerator: $mimeIdGenerator,
            serverNameResolver: $serverNameResolver ?? new ReverseDnsServerNameResolver(cache: $serverNameCache),
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
        $capabilities = $this->ehlo();
        if ($this->useTls) {
            $this->startTls();
            $capabilities = $this->ehlo();
        }
        if ($this->smtpUserName !== '') {
            $this->authenticate(capabilities: $capabilities);
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

    private function ehlo(): SmtpCapabilities
    {
        $this->sendCommand(
            command: 'EHLO ' . $this->getServerName(),
            expectedCode: 250,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'EHLO',
        );

        return SmtpCapabilities::fromEhloReply(reply: $this->lastReply);
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

    private function authenticate(SmtpCapabilities $capabilities): void
    {
        match ($this->chooseAuthMethod(capabilities: $capabilities)) {
            SmtpAuthMethodEnum::LOGIN => $this->authenticateWithLogin(),
            SmtpAuthMethodEnum::PLAIN => $this->authenticateWithPlain(),
            SmtpAuthMethodEnum::XOAUTH2 => $this->authenticateWithXOAuth2(),
        };
    }

    private function chooseAuthMethod(SmtpCapabilities $capabilities): SmtpAuthMethodEnum
    {
        if ($this->authMethod !== null) {
            if (!$capabilities->supports(method: $this->authMethod)) {
                throw new MailerException(
                    message: 'The SMTP server does not announce the authentication method '
                        . $this->authMethod->value . ' (announced: ' . SmtpMailer::describe(capabilities: $capabilities)
                        . ').',
                );
            }

            return $this->authMethod;
        }
        // A server that announces no methods at all is tried as before: LOGIN (XOAUTH2 with a token provider)
        if (!$capabilities->announcesAuth) {
            return $this->oAuthTokenProvider === null ? SmtpAuthMethodEnum::LOGIN : SmtpAuthMethodEnum::XOAUTH2;
        }
        $candidates = $this->oAuthTokenProvider === null
            ? [SmtpAuthMethodEnum::PLAIN, SmtpAuthMethodEnum::LOGIN]
            : [SmtpAuthMethodEnum::XOAUTH2];
        foreach ($candidates as $candidate) {
            if ($capabilities->supports(method: $candidate)) {
                return $candidate;
            }
        }
        throw new MailerException(
            message: 'The SMTP server announces none of the authentication methods '
                . implode(separator: ', ', array: array_map(
                    callback: static fn(SmtpAuthMethodEnum $method): string => $method->value,
                    array: $candidates,
                )) . ' (announced: ' . SmtpMailer::describe(capabilities: $capabilities) . ').',
        );
    }

    private static function describe(SmtpCapabilities $capabilities): string
    {
        return $capabilities->authMethods === [] ? 'none' : implode(separator: ', ', array: $capabilities->authMethods);
    }

    private function authenticateWithLogin(): void
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
            logAs: SmtpMailer::HIDDEN_COMMAND,
        );
        $this->sendCommand(
            command: base64_encode(string: $this->smtpPassword),
            expectedCode: 235,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'the password',
            logAs: SmtpMailer::HIDDEN_COMMAND,
        );
    }

    /**
     * RFC 4616: the user name and the password with the initial response of the command, without authorization
     * identity.
     */
    private function authenticateWithPlain(): void
    {
        $credentials = $this->smtpUserName . $this->smtpPassword;
        if (str_contains(haystack: $credentials, needle: "\0")) {
            throw new MailerException(
                message: 'The user name and the password must not contain a NUL character for AUTH PLAIN.',
            );
        }
        $this->sendCommand(
            command: 'AUTH PLAIN ' . base64_encode(string: "\0" . $this->smtpUserName . "\0" . $this->smtpPassword),
            expectedCode: 235,
            timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT,
            description: 'AUTH PLAIN',
            logAs: 'AUTH PLAIN ' . SmtpMailer::HIDDEN_COMMAND,
        );
    }

    /**
     * The token comes from the provider only now, when the connection is encrypted and the method is chosen. A
     * rejected token is answered with `334` and a JSON error: as Google and Microsoft document, an empty line ends the
     * dialogue with the final error.
     */
    private function authenticateWithXOAuth2(): void
    {
        $tokenProvider = $this->oAuthTokenProvider
            ?? throw new MailerException(message: 'The authentication method XOAUTH2 needs an OAuthTokenProvider.');
        $accessToken = $tokenProvider->getAccessToken();
        if ($accessToken === '') {
            throw new MailerException(message: 'The OAuth token provider returned an empty access token.');
        }
        $payload = 'user=' . $this->smtpUserName . "\x01auth=Bearer " . $accessToken . "\x01\x01";
        $this->writeLine(
            line: 'AUTH XOAUTH2 ' . base64_encode(string: $payload),
            logAs: 'AUTH XOAUTH2 ' . SmtpMailer::HIDDEN_COMMAND,
        );
        $code = $this->readAnyReply(timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT, description: 'AUTH XOAUTH2');
        if ($code === 235) {
            return;
        }
        if ($code === 334) {
            $this->writeLine(line: '', logAs: null);
            $code = $this->readAnyReply(timeoutSeconds: SmtpMailer::COMMAND_TIMEOUT, description: 'AUTH XOAUTH2');
        }
        throw new MailerException(
            message: 'The SMTP server rejected the authentication with XOAUTH2: code ' . $code
                . ' (check the access token and the mailbox).',
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
            $this->writeLine(line: $line, logAs: null);
        }
        $this->sendCommand(
            command: '.',
            expectedCode: 250,
            timeoutSeconds: SmtpMailer::DATA_END_TIMEOUT,
            description: 'the end of the message',
        );
    }

    /**
     * @param string|null $logAs what the log gets instead of the command: for commands that carry credentials
     */
    private function sendCommand(
        string $command,
        int $expectedCode,
        int $timeoutSeconds,
        string $description,
        ?string $logAs = null,
    ): void {
        if (!$this->transport->isOpen()) {
            throw new MailerException(message: 'Tried to send ' . $description . ' without being connected.');
        }
        // A line break would end the command and start another one (SMTP command injection)
        if (strpbrk(string: $command, characters: MailerConstants::CRLF) !== false) {
            throw new MailerException(message: 'The command ' . $description . ' contains a line break.');
        }
        $this->writeLine(line: $command, logAs: $logAs);
        $this->readReply(expectedCode: $expectedCode, timeoutSeconds: $timeoutSeconds, description: $description);
    }

    private function writeLine(string $line, ?string $logAs): void
    {
        $this->log[] = $logAs ?? $line;
        $this->transport->writeLine(line: $line);
    }

    private function readReply(int $expectedCode, int $timeoutSeconds, string $description): void
    {
        $code = $this->readAnyReply(timeoutSeconds: $timeoutSeconds, description: $description);
        if ($code !== $expectedCode) {
            throw new MailerException(
                message: 'Unexpected answer of the SMTP server to ' . $description . ': code ' . $code
                    . ' instead of ' . $expectedCode . '.',
            );
        }
    }

    /**
     * @return int the code of the answer
     */
    private function readAnyReply(int $timeoutSeconds, string $description): int
    {
        $this->lastReply = $this->readLines(timeoutSeconds: $timeoutSeconds);
        if ($this->lastReply === '') {
            throw new MailerException(
                message: 'The SMTP server closed the connection (waiting for the answer to ' . $description . ').',
            );
        }

        return (int) substr(string: $this->lastReply, offset: 0, length: 3);
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
