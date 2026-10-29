<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailbox;

use actra\yuf\mailbox\MimeMessageParser;
use actra\yuf\mailbox\ReceivedMail;
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

    public function testMultipartMixedWithBase64TextAndWithoutAttachment(): void
    {
        $mail = MimeMessageParserTest::parseFixture(name: 'multipart-mixed.eml');

        $this->assertSame('Der Bericht ist angehängt.', $mail->textBody);
        $this->assertSame('reports@example.com', $mail->from?->getPunyEncodedEmail());
        $this->assertSame('', $mail->from->getName());
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
