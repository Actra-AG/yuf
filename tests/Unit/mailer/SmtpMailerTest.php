<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\MailerEncodingEnum;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\SmtpMailer;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\FakeSmtpTransport;
use actra\yuf\tests\Double\mailer\FixedMimeIdGenerator;
use actra\yuf\tests\Double\mailer\FixedServerNameResolver;
use DateTimeImmutable;
use InvalidArgumentException;
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
                'open smtp.example.com:587',
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
        $mailer = $this->mailer(transport: $transport, userName: 'user', useTls: true);

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
        $mailer = $this->mailer(transport: $transport, userName: 'user', useTls: true);

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
        $mailer = $this->mailer(transport: $transport, userName: 'user', useTls: true);

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
            useTls: false,
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

    private function mailer(FakeSmtpTransport $transport, string $userName, bool $useTls = false): SmtpMailer
    {
        return new SmtpMailer(
            serverAddress: '192.0.2.1',
            hostName: 'smtp.example.com',
            smtpUserName: $userName,
            smtpPassword: SmtpMailerTest::PASSWORD,
            serverNameCache: null,
            port: 587,
            useTls: $useTls,
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
