<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\common\FileCache;
use actra\yuf\mailer\MailMailer;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\FixedMimeIdGenerator;
use actra\yuf\tests\Double\mailer\FixedServerNameResolver;
use actra\yuf\tests\Double\mailer\RecordingMailFunction;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MailMailerTest extends TestCase
{
    public function testPassesRecipientsSubjectHeaderAndBodyToMailFunction(): void
    {
        $mailFunction = new RecordingMailFunction();
        $mail = $this->mail(subject: 'Subj');
        $mail->addTo(inputEmail: 'to2@example.com');
        $mail->addCc(inputEmail: 'cc@example.com');
        $mail->addBcc(inputEmail: 'bcc@example.com');

        $mail->send(abstractMailer: $this->mailer(mailFunction: $mailFunction));

        $call = $mailFunction->onlyCall();
        $this->assertSame('To <to@example.com>, to2@example.com', $call['to']);
        $this->assertSame('Subj', $call['subject']);
        $this->assertSame('Hello', $call['message']);
        $this->assertSame('-fsend@example.com', $call['parameters']);
        $this->assertSame(
            implode(
                separator: "\r\n",
                array: [
                    'Date: Thu, 08 Oct 2026 12:00:00 +0000',
                    'From: From <from@example.com>',
                    'Cc: cc@example.com',
                    'Bcc: bcc@example.com',
                    'Reply-To: From <from@example.com>',
                    'Message-ID: <ID@mail.example.com>',
                    'X-Mailer: PHP/' . phpversion(),
                    'X-Priority: 3',
                    'MIME-Version: 1.0',
                    'Content-Type: text/plain; charset=utf-8',
                    'Content-Transfer-Encoding: quoted-printable',
                    '',
                    '',
                ],
            ),
            $call['headers'],
        );
    }

    public function testSubjectIsEncodedForTheShortLinesOfMailFunction(): void
    {
        $mailFunction = new RecordingMailFunction();
        $mail = $this->mail(subject: 'Ein langer Betreff mit Umlauten für Zürich und Genf und Bern');

        $mail->send(abstractMailer: $this->mailer(mailFunction: $mailFunction));

        $this->assertSame(
            "=?utf-8?Q?Ein_langer_Betreff_mit_Umlauten_f=C3=BCr_Z=C3=BCri?=\r\n =?utf-8?Q?ch_und_Genf_und_Bern?=",
            $mailFunction->onlyCall()['subject'],
        );
    }

    public function testLongNonAsciiNameIsFoldedInTheHeader(): void
    {
        $mailFunction = new RecordingMailFunction();
        $mail = new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'Dr. Hans-Peter Müller-Lüdenscheid von und zu Hohenzollern',
            toEmail: 'to@example.com',
            toName: '',
            subject: 'Subj',
            textBody: 'Hello',
        );

        $mail->send(abstractMailer: $this->mailer(mailFunction: $mailFunction));

        $this->assertStringContainsString(
            "\r\nFrom: =?utf-8?Q?Dr=2E_Hans-Peter_M=C3=BCller-L=C3=BCdenscheid_von_?=\r\n"
            . " =?utf-8?Q?und_zu_Hohenzollern?= <from@example.com>\r\n",
            $mailFunction->onlyCall()['headers'],
        );
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function senderProvider(): iterable
    {
        yield 'plain' => ['send@example.com', '-fsend@example.com'];
        yield 'underscore, hyphen and dot' => ['a_b-c.d@example.com', '-fa_b-c.d@example.com'];
        yield 'plus' => ['a+b@example.com', ''];
        yield 'apostrophe' => ["a'b@example.com", ''];
        yield 'ampersand' => ['a&b@example.com', ''];
        yield 'pipe' => ['a|b@example.com', ''];
        yield 'dollar' => ['a$b@example.com', ''];
        yield 'equal sign' => ['a=b@example.com', ''];
        yield 'slash' => ['a/b@example.com', ''];
        yield 'umlaut' => ['ä@example.com', ''];
    }

    #[DataProvider('senderProvider')]
    public function testEnvelopeSenderIsOnlyPassedToTheShellIfItIsSafe(string $sender, string $expected): void
    {
        $mailFunction = new RecordingMailFunction();
        $mail = new TextMail(
            senderEmail: $sender,
            fromEmail: 'from@example.com',
            fromName: 'From',
            toEmail: 'to@example.com',
            toName: 'To',
            subject: 'Subj',
            textBody: 'Hello',
        );

        $mail->send(abstractMailer: $this->mailer(mailFunction: $mailFunction));

        $this->assertSame($expected, $mailFunction->onlyCall()['parameters']);
    }

    public function testRefusedMessageIsAnException(): void
    {
        $this->expectExceptionMessageIs('The mail() function did not accept the message.');

        $this->mail(subject: 'Subj')->send(
            abstractMailer: $this->mailer(mailFunction: new RecordingMailFunction(result: false)),
        );
    }

    public function testHeaderLayout(): void
    {
        $mailer = $this->mailer(mailFunction: new RecordingMailFunction());

        $this->assertFalse($mailer->headerHasTo());
        $this->assertFalse($mailer->headerHasSubject());
        $this->assertTrue($mailer->headerHasBcc());
        $this->assertSame(63, $mailer->getMaxLineLength());
    }

    private function mail(string $subject): TextMail
    {
        return new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'From',
            toEmail: 'to@example.com',
            toName: 'To',
            subject: $subject,
            textBody: 'Hello',
        );
    }

    public function testServerNameComesFromTheServerNameCache(): void
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-mailer-cache-'
            . bin2hex(string: random_bytes(length: 8));
        $cache = new FileCache(directory: $directory);
        $cache->set(key: 'yuf-server-name|192.0.2.1', value: 'cached.example.com', lifetimeInSeconds: 60);

        try {
            $mailer = new MailMailer(serverAddress: '192.0.2.1', serverNameCache: $cache);

            $this->assertSame('cached.example.com', $mailer->getServerName());
        } finally {
            $cache->delete(key: 'yuf-server-name|192.0.2.1');
            rmdir(directory: $directory);
        }
    }

    private function mailer(RecordingMailFunction $mailFunction): MailMailer
    {
        return new MailMailer(
            serverAddress: '192.0.2.1',
            serverNameCache: null,
            mailFunction: $mailFunction,
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-10-08 12:00:00 UTC')),
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(),
        );
    }
}
