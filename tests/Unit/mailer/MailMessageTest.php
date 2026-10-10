<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\attachment\MailerFileAttachment;
use actra\yuf\mailer\attachment\MailerStringAttachment;
use actra\yuf\mailer\HtmlMail;
use actra\yuf\mailer\MailerCharsetEnum;
use actra\yuf\mailer\MailerEncodingEnum;
use actra\yuf\mailer\MailerPriorityEnum;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\CapturingMailer;
use actra\yuf\tests\Double\mailer\ProjectHtmlMail;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the complete message (header and body) for the message types of `AbstractMail`.
 */
final class MailMessageTest extends TestCase
{
    public function testPlainTextMailWithQuotedPrintable(): void
    {
        $message = CapturingMailer::capture(mail: $this->textMail(body: 'Hello'));

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame('Hello', $message->body);
    }

    public function testQuotedPrintableEncodesUmlautsAndWrapsLongLines(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(
                body: "Grüezi Zürich, schöne Grüsse!\nZeile 2 mit = Zeichen\n"
                . str_repeat(string: 'lang ', times: 30),
            ),
        );

        $this->assertSame(
            MailMessageTest::crlf(
                'Gr=C3=BCezi Z=C3=BCrich, sch=C3=B6ne Gr=C3=BCsse!=0AZeile 2 mit =3D Zeichen=',
                '=0Alang lang lang lang lang lang lang lang lang lang lang lang lang lang la=',
                'ng lang lang lang lang lang lang lang lang lang lang lang lang lang lang la=',
                'ng',
            ),
            $message->body,
        );
    }

    public function testBase64Body(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: "Grüezi\nZwei", encoding: MailerEncodingEnum::BASE64),
        );

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: base64',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame("R3LDvGV6aQpad2Vp\r\n", $message->body);
    }

    public function testEightBitBodyKeepsUmlautsAndEndsWithLineBreak(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: "Grüezi\nWorld", encoding: MailerEncodingEnum::EIGHT_BIT),
        );

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: 8bit',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame("Grüezi\r\nWorld\r\n", $message->body);
    }

    public function testSevenBitHasNoTransferEncodingHeader(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: "Hello\nWorld", encoding: MailerEncodingEnum::SEVEN_BIT),
        );

        $this->assertSame(MailMessageTest::header('Content-Type: text/plain; charset=utf-8'), $message->normalizedHeader());
        $this->assertSame("Hello\r\nWorld\r\n", $message->body);
    }

    public function testBinaryBodyIsNotChanged(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: "Hello\nWorld", encoding: MailerEncodingEnum::BINARY),
        );

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: binary',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame("Hello\nWorld", $message->body);
    }

    public function testAsciiCharsetAndHighPriority(): void
    {
        $message = CapturingMailer::capture(
            mail: new TextMail(
                senderEmail: 'send@example.com',
                fromEmail: 'from@example.com',
                fromName: '',
                toEmail: 'to@example.com',
                toName: '',
                subject: 'Subject',
                textBody: 'Hi',
                charSet: MailerCharsetEnum::ASCII,
                priority: MailerPriorityEnum::HIGH,
            ),
        );

        $this->assertSame(
            MailMessageTest::crlf(
                'Date: Thu, 08 Oct 2026 12:00:00 +0000',
                'From: from@example.com',
                'To: to@example.com',
                'Reply-To: from@example.com',
                'Subject: Subject',
                'Message-ID: <ID@mail.example.com>',
                'X-Mailer: PHP/X',
                'X-Priority: 1',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=us-ascii',
                'Content-Transfer-Encoding: quoted-printable',
            ),
            $message->normalizedHeader(),
        );
    }

    public function testLowPriority(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: 'Hi', priority: MailerPriorityEnum::LOW),
        );

        $this->assertStringContainsString("\r\nX-Priority: 5\r\n", $message->header);
    }

    public function testSubjectAndBodyAreTrimmed(): void
    {
        $message = CapturingMailer::capture(mail: $this->textMail(body: "  B\n\n", subject: ' Subject '));

        $this->assertStringContainsString("\r\nSubject: Subject\r\n", $message->header);
        $this->assertSame('B', $message->body);
    }

    public function testWordWrapOfThePlainBody(): void
    {
        $mail = $this->textMail(body: str_repeat(string: 'wort ', times: 30));
        $mail->wordWrap = 40;

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::crlf(
                'wort wort wort wort wort wort wort wort',
                'wort wort wort wort wort wort wort wort',
                'wort wort wort wort wort wort wort wort',
                'wort wort wort wort wort wort',
                '',
            ),
            $message->body,
        );
    }

    public function testWordWrapOfTheAlternativeBodyOnly(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p>x</p>', alternativeBody: str_repeat(string: 'wort ', times: 30));
        $mail->wordWrap = 40;

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                'wort wort wort wort wort wort wort wort',
                'wort wort wort wort wort wort wort wort',
                'wort wort wort wort wort wort wort wort',
                'wort wort wort wort wort wort',
                '',
                '--b1_ID',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                '<p>x</p>',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testHtmlMailWithoutAlternativeIsASingleHtmlPart(): void
    {
        $message = CapturingMailer::capture(mail: $this->htmlMail(htmlBody: '<p>Hi</p>', alternativeBody: ''));

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame('<p>Hi</p>', $message->body);
    }

    public function testHtmlMailWithAlternativeText(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->htmlMail(htmlBody: '<p>Hi Zürich</p>', alternativeBody: 'Hi Zürich'),
        );

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: multipart/alternative;',
                ' boundary="b1_ID"',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                'Hi Z=C3=BCrich',
                '--b1_ID',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                '<p>Hi Z=C3=BCrich</p>',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testTextMailWithAttachment(): void
    {
        $mail = $this->textMail(body: 'Hello');
        $mail->addAttachment(mailerAttachment: $this->stringAttachment());

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: multipart/mixed;',
                ' boundary="b1_ID"',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                'Hello',
                '--b1_ID',
                'Content-Type: text/plain; name=hello.txt',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename=hello.txt',
                '',
                'SGVsbG8gYXR0YWNobWVudA==',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testHtmlMailWithInlineImage(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p><img src="cid:logo.png"></p>', alternativeBody: '');
        $mail->addAttachment(mailerAttachment: $this->inlineImage());

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: multipart/related;',
                ' boundary="b1_ID"',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                '<p><img src=3D"cid:logo.png"></p>',
                '--b1_ID',
                'Content-Type: image/png; name=logo.png',
                'Content-Transfer-Encoding: base64',
                'Content-ID: <logo.png>',
                'Content-Disposition: inline; filename=logo.png',
                '',
                'UE5HREFUQQ==',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testHtmlMailWithAlternativeAndAttachment(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p>Hi</p>', alternativeBody: 'Alt text');
        $mail->addAttachment(mailerAttachment: $this->stringAttachment());

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: multipart/mixed;',
                ' boundary="b1_ID"',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: multipart/alternative;',
                ' boundary="b2_ID"',
                '',
                '--b2_ID',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                'Alt text',
                '--b2_ID',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                '<p>Hi</p>',
                '',
                '--b2_ID--',
                '',
                '--b1_ID',
                'Content-Type: text/plain; name=hello.txt',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename=hello.txt',
                '',
                'SGVsbG8gYXR0YWNobWVudA==',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testHtmlMailWithAlternativeAndInlineImage(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p><img src="cid:logo.png"></p>', alternativeBody: 'Alt text');
        $mail->addAttachment(mailerAttachment: $this->inlineImage());

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: multipart/alternative;',
                ' boundary="b1_ID"',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                'Alt text',
                '--b1_ID',
                'Content-Type: multipart/related;',
                ' boundary="b2_ID";',
                ' type="text/html"',
                '',
                '--b2_ID',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                '<p><img src=3D"cid:logo.png"></p>',
                '--b2_ID',
                'Content-Type: image/png; name=logo.png',
                'Content-Transfer-Encoding: base64',
                'Content-ID: <logo.png>',
                'Content-Disposition: inline; filename=logo.png',
                '',
                'UE5HREFUQQ==',
                '',
                '--b2_ID--',
                '',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testHtmlMailWithAlternativeInlineImageAndAttachment(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p><img src="cid:logo.png"></p>', alternativeBody: 'Alt text');
        $mail->addAttachment(mailerAttachment: $this->inlineImage());
        $mail->addAttachment(mailerAttachment: $this->stringAttachment());

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: multipart/mixed;',
                ' boundary="b1_ID"',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: multipart/alternative;',
                ' boundary="b2_ID"',
                '',
                '--b2_ID',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                'Alt text',
                '--b2_ID',
                'Content-Type: multipart/related;',
                ' boundary="b3_ID";',
                ' type="text/html"',
                '',
                '--b3_ID',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                '<p><img src=3D"cid:logo.png"></p>',
                '--b3_ID',
                'Content-Type: image/png; name=logo.png',
                'Content-Transfer-Encoding: base64',
                'Content-ID: <logo.png>',
                'Content-Disposition: inline; filename=logo.png',
                '',
                'UE5HREFUQQ==',
                '',
                '--b3_ID--',
                '',
                '',
                '--b2_ID--',
                '',
                '--b1_ID',
                'Content-Type: text/plain; name=hello.txt',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename=hello.txt',
                '',
                'SGVsbG8gYXR0YWNobWVudA==',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testHtmlMailWithoutAlternativeWithInlineImageAndAttachment(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p><img src="cid:logo.png"></p>', alternativeBody: '');
        $mail->addAttachment(mailerAttachment: $this->inlineImage());
        $mail->addAttachment(mailerAttachment: $this->stringAttachment());

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::header(
                'Content-Type: multipart/mixed;',
                ' boundary="b1_ID"',
            ),
            $message->normalizedHeader(),
        );
        $this->assertSame(
            MailMessageTest::crlf(
                'This is a multi-part message in MIME format.',
                '',
                '--b1_ID',
                'Content-Type: multipart/related;',
                ' boundary="b2_ID";',
                ' type="text/html"',
                '',
                '--b2_ID',
                'Content-Type: text/html; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
                '',
                '<p><img src=3D"cid:logo.png"></p>',
                '--b2_ID',
                'Content-Type: image/png; name=logo.png',
                'Content-Transfer-Encoding: base64',
                'Content-ID: <logo.png>',
                'Content-Disposition: inline; filename=logo.png',
                '',
                'UE5HREFUQQ==',
                '',
                '--b2_ID--',
                '',
                '--b1_ID',
                'Content-Type: text/plain; name=hello.txt',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename=hello.txt',
                '',
                'SGVsbG8gYXR0YWNobWVudA==',
                '',
                '--b1_ID--',
                '',
            ),
            $message->body,
        );
    }

    public function testFileAttachmentTakesNameAndTypeFromThePath(): void
    {
        $mail = $this->textMail(body: 'Hello');
        $mail->addAttachment(
            mailerAttachment: new MailerFileAttachment(path: __DIR__ . '/../../Fixture/mailer/hello.txt'),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString(
            MailMessageTest::crlf(
                '--b1_ID',
                'Content-Type: text/plain; name=hello.txt',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename=hello.txt',
                '',
                'SGVsbG8gZnJvbSBmaWxlCg==',
                '',
                '--b1_ID--',
            ),
            $message->body,
        );
    }

    public function testFileAttachmentWithOwnNameTypeAndQuotedPrintable(): void
    {
        $mail = $this->textMail(body: 'Hello');
        $mail->addAttachment(
            mailerAttachment: new MailerFileAttachment(
                path: __DIR__ . '/../../Fixture/mailer/hello.txt',
                fileName: 'Mein Text.txt',
                encoding: MailerEncodingEnum::QUOTED_PRINTABLE,
                type: 'text/x-notes',
            ),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString(
            MailMessageTest::crlf(
                '--b1_ID',
                'Content-Type: text/x-notes; name="Mein Text.txt"',
                'Content-Transfer-Encoding: quoted-printable',
                'Content-Disposition: attachment; filename="Mein Text.txt"',
                '',
                'Hello from file=0A',
                '--b1_ID--',
            ),
            $message->body,
        );
    }

    public function testInlineFileAttachmentIsBase64OfTheFile(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p><img src="cid:pixel.png"></p>', alternativeBody: '');
        $mail->addAttachment(
            mailerAttachment: new MailerFileAttachment(
                path: __DIR__ . '/../../Fixture/mailer/pixel.png',
                dispositionInline: true,
            ),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString(
            MailMessageTest::crlf(
                'Content-Type: image/png; name=pixel.png',
                'Content-Transfer-Encoding: base64',
                'Content-ID: <pixel.png>',
                'Content-Disposition: inline; filename=pixel.png',
            ),
            $message->body,
        );
        $this->assertStringContainsString(
            chunk_split(
                string: base64_encode(string: (string) file_get_contents(__DIR__ . '/../../Fixture/mailer/pixel.png')),
                length: 76,
                separator: "\r\n",
            ),
            $message->body,
        );
    }

    public function testAttachmentNamesAreEncodedAndQuoted(): void
    {
        $mail = $this->textMail(body: 'Hello');
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(
                contentString: 'x',
                fileName: 'Rechnung März "2024" (1).pdf',
                type: '',
            ),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString(
            MailMessageTest::crlf(
                'Content-Type: application/pdf; name="=?utf-8?Q?Rechnung_M=C3=A4rz_\"2024\"_(1).pdf?="',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename="=?utf-8?Q?Rechnung_M=C3=A4rz_\"2024\"_(1).pdf?="',
            ),
            $message->body,
        );
    }

    public function testAllRecipientKindsAndCustomHeader(): void
    {
        $mail = new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'Müller, Anna',
            toEmail: 'to@example.com',
            toName: 'To "Quoted" Name',
            subject: 'Subject',
            textBody: 'B',
        );
        $mail->addTo(inputEmail: 'to2@example.com');
        $mail->addCc(inputEmail: 'cc@example.com', inputName: 'Cc Person');
        $mail->addBcc(inputEmail: 'bcc@example.com');
        $mail->addReplyTo(inputEmail: 'reply@example.com', inputName: 'Reply');
        $mail->setConfirmReadingToAddress(inputEmail: 'read@example.com', inputName: 'Reader');
        $mail->addCustomHeader(name: 'X-Custom', value: 'Wert Ü', maxLineLength: 998);

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(
            MailMessageTest::crlf(
                'Date: Thu, 08 Oct 2026 12:00:00 +0000',
                'From: =?utf-8?Q?M=C3=BCller=2C_Anna?= <from@example.com>',
                'To: "To \"Quoted\" Name" <to@example.com>, to2@example.com',
                'Cc: Cc Person <cc@example.com>',
                'Reply-To: Reply <reply@example.com>',
                'Subject: Subject',
                'Message-ID: <ID@mail.example.com>',
                'X-Mailer: PHP/X',
                'X-Priority: 3',
                'Disposition-Notification-To: Reader <read@example.com>',
                'X-Custom: =?utf-8?Q?Wert_=C3=9C?=',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
            ),
            $message->normalizedHeader(),
        );
    }

    public function testMailFunctionMessageHasNoToAndNoSubjectHeader(): void
    {
        $mail = $this->textMail(body: 'B');
        $mail->addCc(inputEmail: 'cc@example.com', inputName: 'Cc Person');

        $message = CapturingMailer::capture(mail: $mail, isSmtpLike: false);

        $this->assertSame(
            MailMessageTest::crlf(
                'Date: Thu, 08 Oct 2026 12:00:00 +0000',
                'From: From Name <from@example.com>',
                'Cc: Cc Person <cc@example.com>',
                'Reply-To: From Name <from@example.com>',
                'Message-ID: <ID@mail.example.com>',
                'X-Mailer: PHP/X',
                'X-Priority: 3',
                'MIME-Version: 1.0',
                'Content-Type: text/plain; charset=utf-8',
                'Content-Transfer-Encoding: quoted-printable',
            ),
            $message->normalizedHeader(),
        );
    }

    public function testAddressesAreLowerCasedAndPunyEncoded(): void
    {
        $message = CapturingMailer::capture(
            mail: new TextMail(
                senderEmail: 'Send@Example.COM',
                fromEmail: 'From@müller.example',
                fromName: 'F',
                toEmail: 'Anna@Bücher.example',
                toName: 'T',
                subject: 'Subject',
                textBody: 'B',
            ),
        );

        $this->assertStringContainsString("\r\nFrom: F <from@xn--mller-kva.example>\r\n", $message->header);
        $this->assertStringContainsString("\r\nTo: T <anna@xn--bcher-kva.example>\r\n", $message->header);
        $this->assertStringContainsString("\r\nReply-To: F <from@xn--mller-kva.example>\r\n", $message->header);
    }

    public function testNonAsciiLocalPartStaysAsEntered(): void
    {
        $message = CapturingMailer::capture(mail: $this->textMail(body: 'B', toEmail: 'ä@example.com'));

        $this->assertStringContainsString("\r\nTo: To Name <ä@example.com>\r\n", $message->header);
    }

    public function testSenderOfTheEnvelopeIsLowerCased(): void
    {
        $mail = new TextMail(
            senderEmail: 'Send@Example.COM',
            fromEmail: 'from@example.com',
            fromName: 'F',
            toEmail: 'to@example.com',
            toName: 'T',
            subject: 'S',
            textBody: 'B',
        );

        $this->assertSame('send@example.com', $mail->sender->getPunyEncodedEmail());
        $this->assertSame('from@example.com', $mail->fromAddress->getPunyEncodedEmail());
    }

    public function testNonAsciiSubjectIsEncodedAsQuotedPrintableWord(): void
    {
        $message = CapturingMailer::capture(mail: $this->textMail(body: 'B', subject: 'Grüezi'));

        $this->assertStringContainsString("\r\nSubject: =?utf-8?Q?Gr=C3=BCezi?=\r\n", $message->header);
    }

    public function testLongNonAsciiSubjectStaysOneEncodedWordForSmtp(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: 'B', subject: str_repeat(string: 'Grüezi Zürich ', times: 12)),
        );

        $this->assertMatchesRegularExpression(
            '/\r\nSubject: =\?utf-8\?Q\?Gr=C3=BCezi_Z=C3=BCrich_(Gr=C3=BCezi_Z=C3=BCrich_){10}'
            . 'Gr=C3=BCezi_Z=C3=BCrich\?=\r\n/',
            $message->header,
        );
    }

    public function testMessageWithoutBodyIsRejected(): void
    {
        $mail = $this->textMail(body: '');

        $this->expectExceptionMessageIs('Message body is empty');

        CapturingMailer::capture(mail: $mail);
    }

    public function testSameMailCannotBeSentTwice(): void
    {
        $mail = $this->textMail(body: 'B');
        CapturingMailer::capture(mail: $mail);

        $this->expectExceptionMessageIs('You cannot send the same email multiple times.');

        CapturingMailer::capture(mail: $mail);
    }

    public function testSecondAttachmentWithTheSameNameIsRejected(): void
    {
        $mail = $this->textMail(body: 'B');
        $mail->addAttachment(mailerAttachment: $this->stringAttachment());

        $this->expectExceptionMessageIs('Attachment with fileName "hello.txt" already exists.');

        $mail->addAttachment(mailerAttachment: $this->stringAttachment());
    }

    public function testSameAddressCannotBeAddedTwice(): void
    {
        $mail = $this->textMail(body: 'B');

        $this->expectExceptionMessageIs('The address is already a recipient (Cc).');

        $mail->addCc(inputEmail: 'to@example.com');
    }

    public function testBlindCopiesAreOnlyInTheHeaderOfMailFunction(): void
    {
        $mail = $this->textMail(body: 'B');
        $mail->addBcc(inputEmail: 'bcc@example.com');

        $smtpMessage = CapturingMailer::capture(mail: $mail, isSmtpLike: false);

        $this->assertStringContainsString("\r\nBcc: bcc@example.com\r\n", $smtpMessage->header);
    }

    public function testBlindCopiesAreNotInTheHeaderOfSmtp(): void
    {
        $mail = $this->textMail(body: 'B');
        $mail->addBcc(inputEmail: 'bcc@example.com');

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringNotContainsString('bcc@example.com', $message->header);
    }

    public function testSubjectOfAnotherScriptIsBase64Encoded(): void
    {
        $message = CapturingMailer::capture(mail: $this->textMail(body: 'B', subject: 'Привет'));

        $this->assertStringContainsString("\r\nSubject: =?utf-8?B?0J/RgNC40LLQtdGC?=\r\n", $message->header);
    }

    public function testLongSubjectOfAnotherScriptIsFoldedIntoSeveralEncodedWords(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(
                body: 'B',
                subject: 'Привет мир, как дела? Это длинная тема письма для проверки '
                . 'переноса строк в заголовке',
            ),
        );

        $this->assertStringContainsString(
            MailMessageTest::crlf(
                'Subject: =?utf-8?B?0J/RgNC40LLQtdGCINC80LjRgCwg0LrQsNC6INC00LXQu9CwPyDQrdGC0L4=?=',
                ' =?utf-8?B?INC00LvQuNC90L3QsNGPINGC0LXQvNCwINC/0LjRgdGM0LzQsCDQtNC70Y8g?=',
                ' =?utf-8?B?0L/RgNC+0LLQtdGA0LrQuCDQv9C10YDQtdC90L7RgdCwINGB0YLRgNC+0Log?=',
                ' =?utf-8?B?0LIg0LfQsNCz0L7Qu9C+0LLQutC1?=',
                'Message-ID: <ID@mail.example.com>',
            ),
            $message->header,
        );
    }

    public function testMailThatExtendsAbstractMailBuildsTheSameMessageAsHtmlMail(): void
    {
        $projectMail = new ProjectHtmlMail(
            toEmail: 'to@example.com',
            htmlBody: '<p>Hi Zürich</p>',
            alternativeBody: 'Hi Zürich',
        );
        $htmlMail = $this->htmlMail(htmlBody: '<p>Hi Zürich</p>', alternativeBody: 'Hi Zürich');

        $this->assertEquals(CapturingMailer::capture(mail: $htmlMail), CapturingMailer::capture(mail: $projectMail));
    }

    public function testVeryLongAsciiSubjectIsFoldedIntoEncodedWords(): void
    {
        $subject = trim(string: str_repeat(string: 'abcdefghi ', times: 120));

        $message = CapturingMailer::capture(mail: $this->textMail(body: 'B', subject: $subject));

        $this->assertSame(
            1,
            preg_match(
                pattern: '/\r\nSubject: (.*?)\r\nMessage-ID: /s',
                subject: $message->header,
                matches: $matches,
            ),
        );
        $this->assertStringContainsString("?=\r\n =?us-ascii?Q?", $matches[1]);
        $this->assertSame($subject, mb_decode_mimeheader(string: $matches[1]));
    }

    public function testSubjectOfOnlyEightBitCharactersIsEncoded(): void
    {
        $message = CapturingMailer::capture(mail: $this->textMail(body: 'B', subject: 'äöüäöü'));

        $this->assertStringContainsString("\r\nSubject: =?utf-8?B?w6TDtsO8w6TDtsO8?=\r\n", $message->header);
    }

    public function testLongNonAsciiNameIsFoldedForMailFunction(): void
    {
        $mail = new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'Dr. Hans-Peter Müller-Lüdenscheid von und zu Hohenzollern',
            toEmail: 'to@example.com',
            toName: 'To',
            subject: 'Subject',
            textBody: 'B',
        );

        $message = CapturingMailer::capture(mail: $mail, isSmtpLike: false);

        $this->assertStringContainsString(
            MailMessageTest::crlf(
                'From: =?utf-8?Q?Dr=2E_Hans-Peter_M=C3=BCller-L=C3=BCdenscheid_von_?=',
                ' =?utf-8?Q?und_zu_Hohenzollern?= <from@example.com>',
                'Reply-To: =?utf-8?Q?Dr=2E_Hans-Peter_M=C3=BCller-L=C3=BCdenscheid_von_?=',
            ),
            $message->header,
        );
    }

    public function testLongLineOfASingleEightBitPartIsQuotedPrintable(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: str_repeat(string: 'x', times: 1100), encoding: MailerEncodingEnum::EIGHT_BIT),
        );

        $this->assertStringContainsString("\r\nContent-Transfer-Encoding: quoted-printable", $message->header);
        $this->assertStringNotContainsString('Content-Transfer-Encoding: 8bit', $message->header);
        foreach (explode(separator: "\r\n", string: $message->body) as $line) {
            $this->assertLessThanOrEqual(76, strlen(string: $line));
        }
        $this->assertSame(
            str_repeat(string: 'x', times: 1100),
            quoted_printable_decode(string: $message->body),
        );
    }

    public function testLongLineOfASingleBase64PartStaysBase64(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->textMail(body: str_repeat(string: 'x', times: 1100), encoding: MailerEncodingEnum::BASE64),
        );

        $this->assertStringContainsString("\r\nContent-Transfer-Encoding: base64", $message->header);
    }

    public function testAttachmentIsNotRepeatedInTheRelatedPart(): void
    {
        $mail = $this->htmlMail(htmlBody: '<p><img src="cid:logo.png"></p>', alternativeBody: 'Alt text');
        $mail->addAttachment(mailerAttachment: $this->stringAttachment());
        $mail->addAttachment(mailerAttachment: $this->inlineImage());

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertSame(1, substr_count(haystack: $message->body, needle: 'filename=hello.txt'));
        $this->assertSame(1, substr_count(haystack: $message->body, needle: 'filename=logo.png'));
    }

    public function testStringAttachmentKeepsItsContentWithWhitespaceAtTheEnds(): void
    {
        $mail = $this->textMail(body: 'Hello');
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(contentString: "  x\n", fileName: 'a.txt', type: ''),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString(base64_encode(string: "  x\n"), $message->body);
    }

    public function testDirectoriesOfTheAttachmentNameAreNotSent(): void
    {
        $mail = $this->textMail(body: 'Hello');
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(contentString: 'y', fileName: '../../etc/passwd', type: ''),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString("Content-Type: application/octet-stream; name=passwd\r\n", $message->body);
        $this->assertStringContainsString("Content-Disposition: attachment; filename=passwd\r\n", $message->body);
        $this->assertStringNotContainsString('etc', $message->body);
    }

    public function testAttachmentNameOfAnotherScriptIsEncoded(): void
    {
        $mail = $this->textMail(body: 'Hello');
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(contentString: 'y', fileName: 'Счёт.pdf', type: ''),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString(
            'Content-Type: application/pdf; name="=?utf-8?B?0KHRh9GR0YIucGRm?="',
            $message->body,
        );
    }

    private function textMail(
        string $body,
        string $subject = 'Subject',
        string $toEmail = 'to@example.com',
        MailerEncodingEnum $encoding = MailerEncodingEnum::QUOTED_PRINTABLE,
        MailerPriorityEnum $priority = MailerPriorityEnum::NORMAL,
    ): TextMail {
        return new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'From Name',
            toEmail: $toEmail,
            toName: 'To Name',
            subject: $subject,
            textBody: $body,
            encoding: $encoding,
            priority: $priority,
        );
    }

    private function htmlMail(string $htmlBody, string $alternativeBody): HtmlMail
    {
        return new HtmlMail(
            senderEmail: 'send@example.com',
            fromEmail: 'from@example.com',
            fromName: 'From Name',
            toEmail: 'to@example.com',
            toName: 'To Name',
            subject: 'Subject',
            htmlBody: $htmlBody,
            alternativeBody: $alternativeBody,
        );
    }

    private function stringAttachment(): MailerStringAttachment
    {
        return new MailerStringAttachment(contentString: 'Hello attachment', fileName: 'hello.txt', type: '');
    }

    private function inlineImage(): MailerStringAttachment
    {
        return new MailerStringAttachment(
            contentString: 'PNGDATA',
            fileName: 'logo.png',
            type: '',
            dispositionInline: true,
        );
    }

    private static function crlf(string ...$lines): string
    {
        return implode(separator: "\r\n", array: $lines);
    }

    private static function header(string ...$contentLines): string
    {
        return MailMessageTest::crlf(
            'Date: Thu, 08 Oct 2026 12:00:00 +0000',
            'From: From Name <from@example.com>',
            'To: To Name <to@example.com>',
            'Reply-To: From Name <from@example.com>',
            'Subject: Subject',
            'Message-ID: <ID@mail.example.com>',
            'X-Mailer: PHP/X',
            'X-Priority: 3',
            'MIME-Version: 1.0',
            ...$contentLines,
        );
    }
}
