<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\MimeMessageParser;
use actra\yuf\mailbox\ReceivedAttachment;
use actra\yuf\mailbox\ReceivedMail;
use actra\yuf\mailer\attachment\MailerFileAttachment;
use actra\yuf\mailer\attachment\MailerStringAttachment;
use actra\yuf\mailer\HtmlMail;
use actra\yuf\tests\Double\mailer\CapturingMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Synthetic fixture mails in tests/Fixture/mailbox/ (LF in the repository, CRLF as delivered by IMAP).
 */
final class MimeMessageParserTest extends TestCase
{
    public function testPlainTextMessage(): void
    {
        $raw = MimeMessageParserTest::fixture(name: 'plain.eml');

        $mail = new MimeMessageParser()->parse(rawMessage: $raw);

        $this->assertSame('<plain-1@example.com>', $mail->messageId);
        $this->assertSame('2026-10-28T10:00:00+01:00', $mail->date?->format(format: DATE_ATOM));
        $this->assertSame('sender@example.com', $mail->from?->getPunyEncodedEmail());
        $this->assertSame('Example Sender', $mail->from->getName());
        $this->assertSame('Plain text message', $mail->subject);
        $this->assertSame("Hello,\n\nthis is a plain text message.\n--\nExample", $mail->textBody);
        $this->assertSame($raw, $mail->rawMessage);
    }

    public function testHeadersAreUnfoldedAndByLowerCaseName(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'plain.eml');

        $this->assertSame(
            [
                'received' => [
                    "from mail.example.com (mail.example.com [192.0.2.10])\tby mx.example.org with ESMTPS;"
                    . ' Wed, 28 Oct 2026 09:59:58 +0100',
                ],
            ],
            array_intersect_key($mail->headers, ['received' => true]),
        );
        $this->assertSame('notifications@example.org', $mail->getHeader(name: 'To'));
        $this->assertNull($mail->getHeader(name: 'Cc'));
    }

    public function testMultipartAlternativePrefersThePlainPart(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'multipart-alternative.eml');

        $this->assertSame(
            'Das Backup von über 2 GB ist abgeschlossen. Diese Zeile ist sehr lang und wurde umgebrochen.',
            $mail->textBody,
        );
        $this->assertSame('Backup abgeschlossen ✔', $mail->subject);
        $this->assertSame('Jürg Beispiel', $mail->from?->getName());
        $this->assertSame('2026-10-28T11:30:00+00:00', $mail->date?->format(format: DATE_ATOM));
    }

    public function testMultipartAlternativeHasTheHtmlPart(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'multipart-alternative.eml');

        $this->assertSame('<p>Das Backup von über 2 GB ist <b>abgeschlossen</b>.</p>', $mail->htmlBody);
        $this->assertSame([], $mail->attachments);
    }

    public function testMultipartMixedWithBase64TextAndWithoutAttachment(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'multipart-mixed.eml');

        $this->assertSame('Der Bericht ist angehängt.', $mail->textBody);
        $this->assertSame('reports@example.com', $mail->from?->getPunyEncodedEmail());
        $this->assertSame('', $mail->from->getName());
    }

    public function testMultipartMixedHasTheHtmlPartAndTheAttachment(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'multipart-mixed.eml');

        $this->assertSame('<p>Der Bericht ist angeh&auml;ngt.</p>', $mail->htmlBody);
        $this->assertSame(
            [['report.txt', 'text/plain', false, null, 'Secret attachment text', 22]],
            MimeMessageParserTest::describeAttachments(mail: $mail),
        );
    }

    public function testMultipartRelatedWithInlineImageAndEncodedFileNames(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'multipart-related.eml');

        $this->assertSame('<p>Grüsse aus Zürich <img src="cid:logo@example.com"></p>', $mail->htmlBody);
        $this->assertSame('Grüsse aus Zürich', $mail->textBody);
        $this->assertSame(
            [
                [null, 'image/png', true, 'logo@example.com', MimeMessageParserTest::pixel(), 70],
                ['Bericht für Zürich.pdf', 'application/pdf', false, null, '%PDF-1.4', 8],
                ['Müller.csv', 'text/csv', false, null, 'a;b', 3],
                ['notes.txt', 'text/plain', false, null, 'Note', 4],
                [null, 'application/octet-stream', false, null, 'data', 4],
            ],
            MimeMessageParserTest::describeAttachments(mail: $mail),
        );
    }

    public function testPlainMessageHasNoHtmlAndNoAttachments(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'plain.eml');

        $this->assertNull($mail->htmlBody);
        $this->assertSame([], $mail->attachments);
    }

    public function testHtmlOnlyMessageHasTheHtmlBody(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'html-only-8bit.eml');

        $this->assertStringStartsWith('<', $mail->htmlBody ?? '');
        $this->assertStringContainsString('übersteigt bald das Limit', $mail->htmlBody ?? '');
    }

    public function testImageOnlyMessageIsAnAttachment(): void
    {
        $raw = "Content-Type: image/png; name=pixel.png\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . base64_encode(string: MimeMessageParserTest::pixel());

        $mail = new MimeMessageParser()->parse(rawMessage: $raw);

        $this->assertSame(
            [['pixel.png', 'image/png', false, null, MimeMessageParserTest::pixel(), 70]],
            MimeMessageParserTest::describeAttachments(mail: $mail),
        );
        $this->assertSame('', $mail->textBody);
    }

    /**
     * @param bool $isSmtpLike `false`: header lines of at most 63 characters, the encoded file names are folded
     */
    #[DataProvider('mailerProvider')]
    public function testMessageOfHtmlMailIsParsedBack(bool $isSmtpLike): void
    {
        $htmlMail = new HtmlMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'Example Sender',
            toEmail: 'to@example.com',
            toName: '',
            subject: 'Rechnung für Oktober',
            htmlBody: '<p>Die Rechnung für Oktober <img src="cid:pixel.png"></p>',
            alternativeBody: 'Die Rechnung für Oktober',
        );
        $htmlMail->addAttachment(mailerAttachment: new MailerStringAttachment(
            contentString: 'Betrag: 100 CHF',
            fileName: 'Rechnung für Oktober mit einem sehr langen Dateinamen.txt',
            type: '',
        ));
        $htmlMail->addAttachment(mailerAttachment: new MailerFileAttachment(
            path: __DIR__ . '/../../Fixture/mailer/pixel.png',
            dispositionInline: true,
        ));
        $message = CapturingMailer::capture(mail: $htmlMail, isSmtpLike: $isSmtpLike);

        $mail = new MimeMessageParser()->parse(rawMessage: $message->header . "\r\n\r\n" . $message->body);

        $this->assertSame('Die Rechnung für Oktober', $mail->textBody);
        $this->assertSame('<p>Die Rechnung für Oktober <img src="cid:pixel.png"></p>', $mail->htmlBody);
        $this->assertSame(
            [
                ['pixel.png', 'image/png', true, 'pixel.png', MimeMessageParserTest::pixel(), 70],
                [
                    'Rechnung für Oktober mit einem sehr langen Dateinamen.txt',
                    'text/plain',
                    false,
                    null,
                    'Betrag: 100 CHF',
                    15,
                ],
            ],
            MimeMessageParserTest::describeAttachments(mail: $mail),
        );
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function mailerProvider(): iterable
    {
        yield 'SMTP' => [true];
        yield 'mail()' => [false];
    }

    public function testIso88591IsConvertedToUtf8(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'iso-8859-1.eml');

        $this->assertSame('Grüsse aus Zürich', $mail->subject);
        $this->assertSame('Müller AG', $mail->from?->getName());
        $this->assertSame("Grüsse aus Zürich, schönes Wochenende!\nPreis: 10 €", $mail->textBody);
        $this->assertSame('2026-10-26T07:00:00+00:00', $mail->date?->format(format: DATE_ATOM));
    }

    public function testHtmlOnly8BitMessageOfAServerNotification(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'html-only-8bit.eml');

        $this->assertSame(
            'Server host.example.com: Die Größe des Abonnements example.net übersteigt das Limit (95 %)',
            $mail->subject,
        );
        $this->assertSame('server@host.example.com', $mail->from?->getPunyEncodedEmail());
        $this->assertSame('<20261029064501.12345@host.example.com>', $mail->messageId);
        $this->assertSame(
            "Hallo,\n\ndas Abonnement example.net hat 95 % des Speicherplatzes erreicht & übersteigt bald das Limit."
            . "\n\n"
            . "Ressource Wert\nSpeicher 9.5 GB\n\nZeile 1\nZeile 2",
            $mail->textBody,
        );
    }

    public function testHtmlOnlyQuotedPrintableMessageKeepsLinkTargets(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'html-only-quoted-printable-links.eml');

        $this->assertSame(
            'Vulnerabilities were found on a site'
            . ' <https://host.example.com:8443/modules/wp-toolkit/index.php/index/list/id/23/checkSecurity/1/activeTab'
            . "/vulnerable-components>.\n\n"
            . "Plugin Version\nexample-plugin <https://example.com/plugins/example-plugin?ref=mail&lang=en> 1.2.3"
            . "\n\nContact us",
            $mail->textBody,
        );
    }

    public function testLfLineBreaksAreReadLikeCrlf(): void
    {
        $lf = MimeMessageParserTest::fixture(name: 'multipart-mixed.eml', lineBreak: "\n");

        $mail = new MimeMessageParser()->parse(rawMessage: $lf);

        $this->assertSame('Der Bericht ist angehängt.', $mail->textBody);
        $this->assertSame($lf, $mail->rawMessage);
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('malformedMessageProvider')]
    public function testMalformedMessagesDoNotThrow(string $raw, array $expected): void
    {
        $mail = new MimeMessageParser()->parse(rawMessage: $raw);

        $this->assertSame(
            $expected,
            [
                'messageId' => $mail->messageId ?? 'null',
                'date' => $mail->date?->format(format: DATE_ATOM) ?? 'null',
                'from' => $mail->from?->getPunyEncodedEmail() ?? 'null',
                'subject' => $mail->subject,
                'textBody' => $mail->textBody,
            ],
        );
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function malformedMessageProvider(): iterable
    {
        $empty = ['messageId' => 'null', 'date' => 'null', 'from' => 'null', 'subject' => '', 'textBody' => ''];

        yield 'empty' => ['', $empty];
        yield 'headers only' => ["Subject: Only headers\r\n", [...$empty, 'subject' => 'Only headers']];
        yield 'body only' => ["\r\nJust a body", [...$empty, 'textBody' => 'Just a body']];
        yield 'invalid from and date' => [
            "From: not an address\r\nDate: yesterday\r\nMessage-ID:\r\n\r\nText",
            [...$empty, 'textBody' => 'Text'],
        ];
        yield 'impossible date' => ["Date: 31 Feb 2026 10:00:00 +0000\r\n\r\n", $empty];
        yield 'multipart without boundary' => ["Content-Type: multipart/mixed\r\n\r\n--x\r\n\r\nText\r\n--x--", $empty];
        yield 'image only' => ["Content-Type: image/png\r\nContent-Transfer-Encoding: base64\r\n\r\niVBORw0K", $empty];
        yield 'invalid UTF-8' => [
            "Content-Type: text/plain; charset=utf-8\r\n\r\nGr\xFCsse",
            [...$empty, 'textBody' => 'Grüsse'],
        ];
        yield 'unknown charset' => [
            "Content-Type: text/plain; charset=x-unknown\r\n\r\nText",
            [...$empty, 'textBody' => 'Text'],
        ];
        yield 'from with comment' => [
            "From: info@example.com (Example Info)\r\n\r\n",
            [...$empty, 'from' => 'info@example.com'],
        ];
        yield 'invalid base64' => [
            "Content-Type: text/plain\r\nContent-Transfer-Encoding: base64\r\n\r\n!!!",
            $empty,
        ];
    }

    public function testNestedMultipartsAreLimited(): void
    {
        $raw = 'Content-Type: text/plain' . "\r\n\r\nInnermost";
        for ($depth = 0; $depth < 30; $depth++) {
            $raw = 'Content-Type: multipart/mixed; boundary="b' . $depth . '"' . "\r\n\r\n--b" . $depth . "\r\n" . $raw
                . "\r\n--b" . $depth . '--';
        }

        $mail = new MimeMessageParser()->parse(rawMessage: $raw);

        $this->assertSame('', $mail->textBody);
    }

    public function testInlineTextPartsOfMultipartMixedAreJoined(): void
    {
        $raw = "Content-Type: multipart/mixed; boundary=b\r\n\r\npreamble\r\n--b\r\n\r\nFirst\r\n--b\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\nContent-Disposition: inline\r\n\r\n"
            . "Second\r\n--b--\r\nepilogue";

        $mail = new MimeMessageParser()->parse(rawMessage: $raw);

        $this->assertSame("First\n\nSecond", $mail->textBody);
    }

    /**
     * @return list<array{string|null, string, bool, string|null, string, int}>
     */
    private static function describeAttachments(ReceivedMail $mail): array
    {
        return array_map(
            callback: static fn(ReceivedAttachment $attachment): array => [
                $attachment->fileName,
                $attachment->contentType,
                $attachment->isInline,
                $attachment->contentId,
                $attachment->content,
                $attachment->getSize(),
            ],
            array: $mail->attachments,
        );
    }

    private static function pixel(): string
    {
        $content = file_get_contents(filename: __DIR__ . '/../../Fixture/mailer/pixel.png');
        if ($content === false) {
            throw new RuntimeException(message: 'Missing fixture pixel.png');
        }

        return $content;
    }

    private static function parseFixture(string $name): ReceivedMail
    {
        return new MimeMessageParser()->parse(rawMessage: MimeMessageParserTest::fixture(name: $name));
    }

    private static function fixture(string $name, string $lineBreak = "\r\n"): string
    {
        $content = file_get_contents(filename: __DIR__ . '/../../Fixture/mailbox/' . $name);
        if ($content === false) {
            throw new RuntimeException(message: 'Missing fixture ' . $name);
        }

        return str_replace(search: "\n", replace: $lineBreak, subject: $content);
    }
}
