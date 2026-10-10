<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\FixedServerNameResolver;
use actra\yuf\mailer\MailerEncodingEnum;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\SmtpEncryptionEnum;
use actra\yuf\mailer\SmtpMailer;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\FakeSmtpTransport;
use actra\yuf\tests\Double\mailer\FixedMimeIdGenerator;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The SMTP dialogue with a scripted server. The dialogue of the delivery without TLS was recorded from the mailer
 * before the refactoring (local socket server); TLS and the failures are checked against the protocol.
 */
final class SmtpMailerTest extends TestCase
{
    private const string PASSWORD = 'secret-pass';

    public function testDialogueWithAuthentication(): void
    {
        $transport = new FakeSmtpTransport(
            replies: SmtpMailerTest::replies(authenticated: true),
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user');

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(
            [
                'open localhost:587',
                'write EHLO mail.example.com',
                'write AUTH LOGIN',
                'write dXNlcg==',
                'write ' . base64_encode(string: SmtpMailerTest::PASSWORD),
                'write MAIL FROM: <send@example.com>',
                'write RCPT TO: <to@example.com>',
                'write RCPT TO: <cc@example.com>',
                'write RCPT TO: <bcc@example.com>',
                'write DATA',
                ...array_map(
                    callback: static fn(string $line): string => 'write ' . $line,
                    array: SmtpMailerTest::expectedMessageLines(),
                ),
                'write .',
                'write QUIT',
                'close',
            ],
            $transport->events,
        );
        $this->assertSame("221 bye\r\n", $mailer->lastReply);
    }

    public function testDialogueWithoutAuthenticationWhenThereIsNoUserName(): void
    {
        $transport = new FakeSmtpTransport(replies: SmtpMailerTest::replies(authenticated: false));
        $mailer = $this->mailer(transport: $transport, userName: '');

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(
            [
                'EHLO mail.example.com',
                'MAIL FROM: <send@example.com>',
                'RCPT TO: <to@example.com>',
                'RCPT TO: <cc@example.com>',
                'RCPT TO: <bcc@example.com>',
                'DATA',
            ],
            array_slice(array: $transport->writtenLines(), offset: 0, length: 6),
        );
    }

    public function testBlindCopiesAreRecipientsButNotInTheHeader(): void
    {
        $transport = new FakeSmtpTransport(replies: SmtpMailerTest::replies(authenticated: false));

        $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: ''));

        $this->assertContains('RCPT TO: <bcc@example.com>', $transport->writtenLines());
        $this->assertNotContains('Bcc: bcc@example.com', $transport->writtenLines());
        $this->assertContains('Cc: cc@example.com', $transport->writtenLines());
    }

    public function testStartTlsComesBeforeTheCredentials(): void
    {
        $transport = new FakeSmtpTransport(
            replies: [
                "220 mx ready\r\n",
                "250-mx\r\n250 STARTTLS\r\n",
                "220 go ahead\r\n",
                "250-mx\r\n250 AUTH LOGIN\r\n",
                "334 VXNlcm5hbWU6\r\n",
                "334 UGFzc3dvcmQ6\r\n",
                "235 ok\r\n",
                ...array_slice(array: SmtpMailerTest::replies(authenticated: false), offset: 2),
            ],
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user', encryption: SmtpEncryptionEnum::STARTTLS);

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(
            [
                'open smtp.example.com:587',
                'write EHLO mail.example.com',
                'write STARTTLS',
                'tls',
                'write EHLO mail.example.com',
                'write AUTH LOGIN',
                'write dXNlcg==',
                'write ' . base64_encode(string: SmtpMailerTest::PASSWORD),
                'write MAIL FROM: <send@example.com>',
            ],
            array_slice(array: $transport->events, offset: 0, length: 9),
        );
    }

    public function testFailedTlsHandshakeAbortsBeforeAnythingIsSent(): void
    {
        $transport = new FakeSmtpTransport(
            replies: ["220 mx ready\r\n", "250 mx\r\n", "220 go ahead\r\n"],
            tlsSucceeds: false,
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user', encryption: SmtpEncryptionEnum::STARTTLS);

        try {
            $this->mail()->send(abstractMailer: $mailer);
            SmtpMailerTest::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertStringContainsString('TLS connection', $exception->getMessage());
        }

        $this->assertSame(
            ['open smtp.example.com:587', 'write EHLO mail.example.com', 'write STARTTLS', 'tls', 'close'],
            $transport->events,
        );
    }

    public function testServerWithoutStartTlsIsNotUsedInPlainText(): void
    {
        $transport = new FakeSmtpTransport(
            replies: ["220 mx ready\r\n", "250 mx\r\n", "502 5.5.1 command not implemented\r\n"],
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user', encryption: SmtpEncryptionEnum::STARTTLS);

        try {
            $this->mail()->send(abstractMailer: $mailer);
            SmtpMailerTest::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'Unexpected answer of the SMTP server to STARTTLS: code 502 instead of 220.',
                $exception->getMessage(),
            );
        }

        $this->assertNotContains('AUTH LOGIN', $transport->writtenLines());
        $this->assertNotContains('tls', $transport->events);
        $this->assertSame('close', $transport->lastEvent());
    }

    public function testRejectedCredentialsAreNeitherInTheExceptionNorInTheLog(): void
    {
        $transport = new FakeSmtpTransport(
            replies: ["220 mx ready\r\n", "250 mx\r\n", "334 x\r\n", "334 y\r\n", "535 5.7.8 bad credentials\r\n"],
        );
        $mailer = $this->mailer(transport: $transport, userName: 'user');

        try {
            $this->mail()->send(abstractMailer: $mailer);
            SmtpMailerTest::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'Unexpected answer of the SMTP server to the password: code 535 instead of 235.',
                $exception->getMessage(),
            );
        }

        $this->assertSame("535 5.7.8 bad credentials\r\n", $mailer->lastReply);
        $this->assertSame(
            [
                'EHLO mail.example.com',
                "250 mx\r\n",
                'AUTH LOGIN',
                "334 x\r\n",
                '(hidden)',
                "334 y\r\n",
                '(hidden)',
                "535 5.7.8 bad credentials\r\n",
            ],
            array_slice(array: $mailer->log, offset: 1),
        );
        $this->assertStringNotContainsString(SmtpMailerTest::PASSWORD, implode(separator: '', array: $mailer->log));
        $this->assertStringNotContainsString(
            base64_encode(string: SmtpMailerTest::PASSWORD),
            implode(separator: '', array: $mailer->log),
        );
    }

    public function testRejectedRecipientAbortsAndClosesTheConnection(): void
    {
        $transport = new FakeSmtpTransport(
            replies: ["220 mx ready\r\n", "250 mx\r\n", "250 ok\r\n", "550 5.1.1 no such user\r\n"],
        );
        $mailer = $this->mailer(transport: $transport, userName: '');

        try {
            $this->mail()->send(abstractMailer: $mailer);
            SmtpMailerTest::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame(
                'Unexpected answer of the SMTP server to RCPT TO: code 550 instead of 250.',
                $exception->getMessage(),
            );
        }

        $this->assertSame("550 5.1.1 no such user\r\n", $mailer->lastReply);
        $this->assertSame('close', $transport->lastEvent());
        $this->assertNotContains('DATA', $transport->writtenLines());
    }

    public function testGreetingWithAnErrorCode(): void
    {
        $transport = new FakeSmtpTransport(replies: ["554 no service\r\n"]);

        $this->expectExceptionMessageIs(
            'Unexpected answer of the SMTP server to the connection: code 554 instead of 220.',
        );

        $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: ''));
    }

    public function testConnectionClosedByTheServer(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 ready\r\n"]);

        $this->expectExceptionMessageIs('The SMTP server closed the connection (waiting for the answer to EHLO).');

        $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: ''));
    }

    public function testConnectionErrorIsReportedAndNothingIsSent(): void
    {
        $transport = new FakeSmtpTransport(replies: [], openFails: true);

        try {
            $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: ''));
            SmtpMailerTest::fail('The delivery must be aborted.');
        } catch (MailerException $exception) {
            $this->assertSame('Socket connection error: smtp.example.com', $exception->getMessage());
        }

        $this->assertSame(['open smtp.example.com:587'], $transport->events);
    }

    public function testLineBreakInTheServerNameCannotStartAnotherCommand(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 ready\r\n", "250 ok\r\n"]);
        $mailer = new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: 'smtp.example.com',
            smtpUserName: '',
            smtpPassword: '',
            serverNameCache: null,
            encryption: SmtpEncryptionEnum::NONE,
            transport: $transport,
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(
                serverName: "mail.example.com\r\nMAIL FROM:<x@example.com>",
            ),
        );

        try {
            $this->mail()->send(abstractMailer: $mailer);
            SmtpMailerTest::fail('The delivery must be aborted.');
        } catch (MailerException) {
            // The message id is part of the header, so the message is not even built
        }

        $this->assertSame([], $transport->events);
    }

    public function testLinesThatStartWithADotAreStuffed(): void
    {
        $transport = new FakeSmtpTransport(
            replies: [
                "220 mx ready\r\n",
                "250 mx\r\n",
                "250 ok\r\n",
                "250 ok\r\n",
                "354 go\r\n",
                "250 queued\r\n",
                "221 bye\r\n",
            ],
        );
        $mail = new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'From',
            toEmail: 'to@example.com',
            toName: 'To',
            subject: 'Subj',
            textBody: ".start\nmid\n..two\n.",
            encoding: MailerEncodingEnum::EIGHT_BIT,
        );

        $mail->send(abstractMailer: $this->mailer(transport: $transport, userName: ''));

        $this->assertSame(
            ['..start', 'mid', '...two', '..', '', '.'],
            array_slice(array: $transport->writtenLines(), offset: -7, length: 6),
        );
    }

    public function testEachDeliveryStartsWithAnEmptyLogAndReply(): void
    {
        $transport = new FakeSmtpTransport(
            replies: [...SmtpMailerTest::replies(authenticated: false), ...SmtpMailerTest::replies(authenticated: false)],
        );
        $mailer = $this->mailer(transport: $transport, userName: '');
        $this->mail()->send(abstractMailer: $mailer);
        $logSize = count(value: $mailer->log);

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertCount($logSize, $mailer->log);
    }

    public function testCommandsAreNotLongerThanTheirTimeoutAllows(): void
    {
        $transport = new FakeSmtpTransport(replies: SmtpMailerTest::replies(authenticated: false));

        $this->mail()->send(abstractMailer: $this->mailer(transport: $transport, userName: ''));

        $this->assertSame([300, 10, 300, 300, 300, 300, 120, 600, 60], $transport->readTimeouts);
    }

    public function testImplicitTlsConnectsEncryptedAndSendsNoStartTls(): void
    {
        $transport = new FakeSmtpTransport(
            replies: [
                "220 mx ready\r\n",
                "250-mx\r\n250 AUTH LOGIN\r\n",
                "334 VXNlcm5hbWU6\r\n",
                "334 UGFzc3dvcmQ6\r\n",
                "235 ok\r\n",
                ...array_slice(array: SmtpMailerTest::replies(authenticated: false), offset: 2),
            ],
        );
        $mailer = $this->mailer(
            transport: $transport,
            userName: 'user',
            encryption: SmtpEncryptionEnum::IMPLICIT_TLS,
            port: 465,
        );

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame(
            ['open tls smtp.example.com:465', 'write EHLO mail.example.com', 'write AUTH LOGIN'],
            array_slice(array: $transport->events, offset: 0, length: 3),
        );
        $this->assertNotContains('tls', $transport->events);
        $this->assertNotContains('write STARTTLS', $transport->events);
    }

    public function testDefaultsAreStartTlsOnPort587(): void
    {
        $transport = new FakeSmtpTransport(replies: ["220 mx ready\r\n", "250 STARTTLS\r\n", "502 no\r\n"]);
        $mailer = new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: 'smtp.example.com',
            smtpUserName: 'user',
            smtpPassword: SmtpMailerTest::PASSWORD,
            serverNameCache: null,
            transport: $transport,
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(),
        );

        try {
            $this->mail()->send(abstractMailer: $mailer);
        } catch (MailerException) {
            // Only the start of the dialogue is of interest
        }

        $this->assertSame(
            ['open smtp.example.com:587', 'write EHLO mail.example.com', 'write STARTTLS'],
            array_slice(array: $transport->events, offset: 0, length: 3),
        );
    }

    public function testImplicitTlsUsesPort465ByDefault(): void
    {
        $transport = new FakeSmtpTransport(replies: ["421 bye\r\n"]);
        $mailer = new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: 'smtp.example.com',
            smtpUserName: 'user',
            smtpPassword: SmtpMailerTest::PASSWORD,
            serverNameCache: null,
            encryption: SmtpEncryptionEnum::IMPLICIT_TLS,
            transport: $transport,
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(),
        );

        try {
            $this->mail()->send(abstractMailer: $mailer);
        } catch (MailerException) {
            // Only the connection is of interest
        }

        $this->assertSame('open tls smtp.example.com:465', $transport->events[0] ?? '');
    }

    public function testAuthenticationWithoutEncryptionIsRefusedForARemoteServer(): void
    {
        $this->expectExceptionMessageIs(
            'The credentials must not be sent without encryption to smtp.example.com: use SmtpEncryptionEnum::STARTTLS'
            . ' or IMPLICIT_TLS (without encryption only for localhost).',
        );

        $this->mailer(
            transport: new FakeSmtpTransport(replies: []),
            userName: 'user',
            encryption: SmtpEncryptionEnum::NONE,
            hostName: 'smtp.example.com',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function loopbackHostProvider(): iterable
    {
        yield 'localhost' => ['localhost'];
        yield 'localhost upper case' => ['LOCALHOST'];
        yield 'IPv4 loopback' => ['127.0.0.1'];
        yield 'other IPv4 loopback address' => ['127.12.0.5'];
        yield 'IPv6 loopback' => ['::1'];
        yield 'IPv6 loopback in brackets' => ['[::1]'];
        yield 'IPv6 loopback long form' => ['0:0:0:0:0:0:0:1'];
    }

    #[DataProvider('loopbackHostProvider')]
    public function testAuthenticationWithoutEncryptionIsAllowedOnLoopback(string $hostName): void
    {
        $mailer = $this->mailer(
            transport: new FakeSmtpTransport(replies: []),
            userName: 'user',
            encryption: SmtpEncryptionEnum::NONE,
            hostName: $hostName,
        );

        $this->assertSame([], $mailer->log);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function remoteHostProvider(): iterable
    {
        yield 'name' => ['smtp.example.com'];
        yield 'name that starts with localhost' => ['localhost.example.com'];
        yield 'name that looks like a loopback address' => ['127.0.0.1.example.com'];
        yield 'public IPv4' => ['192.0.2.1'];
        yield 'any address' => ['0.0.0.0'];
        yield 'IPv4 address next to the loopback range' => ['128.0.0.1'];
        yield 'IPv6 address' => ['2001:db8::1'];
        yield 'IPv4 mapped IPv6 address of a remote server' => ['::ffff:192.0.2.1'];
    }

    #[DataProvider('remoteHostProvider')]
    public function testAuthenticationWithoutEncryptionIsRefusedOnOtherHosts(string $hostName): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->mailer(
            transport: new FakeSmtpTransport(replies: []),
            userName: 'user',
            encryption: SmtpEncryptionEnum::NONE,
            hostName: $hostName,
        );
    }

    public function testDeliveryWithoutAuthenticationNeedsNoEncryption(): void
    {
        $transport = new FakeSmtpTransport(replies: SmtpMailerTest::replies(authenticated: false));
        $mailer = $this->mailer(
            transport: $transport,
            userName: '',
            encryption: SmtpEncryptionEnum::NONE,
            hostName: 'smtp.example.com',
        );

        $this->mail()->send(abstractMailer: $mailer);

        $this->assertSame('open smtp.example.com:587', $transport->events[0] ?? '');
    }

    public function testInvalidHostNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: "smtp.example.com\r\nX",
            smtpUserName: '',
            smtpPassword: '',
            serverNameCache: null,
        );
    }

    public function testInvalidPortIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: 'smtp.example.com',
            smtpUserName: '',
            smtpPassword: '',
            serverNameCache: null,
            port: 70000,
        );
    }

    public function testHasNoBlindCopyHeaderButToAndSubject(): void
    {
        $mailer = $this->mailer(transport: new FakeSmtpTransport(replies: []), userName: '');

        $this->assertTrue($mailer->headerHasTo());
        $this->assertTrue($mailer->headerHasSubject());
        $this->assertFalse($mailer->headerHasBcc());
        $this->assertSame(998, $mailer->getMaxLineLength());
    }

    private function mailer(
        FakeSmtpTransport $transport,
        string $userName,
        SmtpEncryptionEnum $encryption = SmtpEncryptionEnum::NONE,
        ?string $hostName = null,
        int $port = 587,
    ): SmtpMailer {
        // Credentials without encryption are only allowed for the same host
        $defaultHostName = $userName !== '' && $encryption === SmtpEncryptionEnum::NONE
            ? 'localhost'
            : 'smtp.example.com';

        return new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: $hostName ?? $defaultHostName,
            smtpUserName: $userName,
            smtpPassword: SmtpMailerTest::PASSWORD,
            serverNameCache: null,
            port: $port,
            encryption: $encryption,
            transport: $transport,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-10-08 12:00:00 UTC')),
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(),
        );
    }

    private function mail(): TextMail
    {
        $mail = new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'From',
            toEmail: 'to@example.com',
            toName: 'To',
            subject: 'Subj',
            textBody: 'Hello',
        );
        $mail->addCc(inputEmail: 'cc@example.com');
        $mail->addBcc(inputEmail: 'bcc@example.com');

        return $mail;
    }

    /**
     * @return list<string>
     */
    private static function expectedMessageLines(): array
    {
        return [
            'Date: Thu, 08 Oct 2026 12:00:00 +0000',
            'From: From <from@example.com>',
            'To: To <to@example.com>',
            'Cc: cc@example.com',
            'Reply-To: From <from@example.com>',
            'Subject: Subj',
            'Message-ID: <ID@mail.example.com>',
            'X-Mailer: PHP/' . phpversion(),
            'X-Priority: 3',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=utf-8',
            'Content-Transfer-Encoding: quoted-printable',
            '',
            'Hello',
        ];
    }

    /**
     * @return list<string> the answers of a server that accepts the delivery to three recipients
     */
    private static function replies(bool $authenticated): array
    {
        return [
            "220 mx ready\r\n",
            $authenticated ? "250-mx\r\n250 AUTH LOGIN\r\n" : "250 mx\r\n",
            ...($authenticated ? ["334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "235 ok\r\n"] : []),
            "250 ok\r\n",
            "250 ok\r\n",
            "250 ok\r\n",
            "250 ok\r\n",
            "354 go\r\n",
            "250 queued\r\n",
            "221 bye\r\n",
        ];
    }
}
