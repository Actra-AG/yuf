<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\attachment\MailerStringAttachment;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\MailerHeader;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\CapturingMailer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Line breaks of user input must never start a new header line.
 */
final class MailHeaderInjectionTest extends TestCase
{
    public function testLineBreaksInTheSubjectAreRemoved(): void
    {
        $message = CapturingMailer::capture(mail: $this->mail(subject: "Hello\r\nBcc: evil@example.com"));

        $this->assertStringContainsString("\r\nSubject: HelloBcc: evil@example.com\r\n", $message->header);
        $this->assertStringNotContainsString("\r\nBcc:", $message->header);
    }

    public function testBareLineFeedAndCarriageReturnInTheSubjectAreRemoved(): void
    {
        $message = CapturingMailer::capture(mail: $this->mail(subject: "Hello\nX-Evil: 1\rX-Evil2: 2"));

        $this->assertStringContainsString("\r\nSubject: HelloX-Evil: 1X-Evil2: 2\r\n", $message->header);
        $this->assertStringNotContainsString("\r\nX-Evil", $message->header);
    }

    public function testLineBreaksInNamesAreRemoved(): void
    {
        $message = CapturingMailer::capture(
            mail: $this->mail(fromName: "Evil\r\nBcc: evil@example.com", toName: "T\nX: y"),
        );

        $this->assertStringContainsString(
            "\r\nFrom: \"EvilBcc: evil@example.com\" <from@example.com>\r\n",
            $message->header,
        );
        $this->assertStringContainsString("\r\nTo: \"TX: y\" <to@example.com>\r\n", $message->header);
        $this->assertStringNotContainsString("\r\nBcc:", $message->header);
    }

    public function testLineBreaksInAnAddressAreRejected(): void
    {
        $this->expectException(MailerException::class);

        $this->mail(fromEmail: "from@example.com\r\nBcc: evil@example.com");
    }

    public function testLineBreaksInTheRecipientAreRejected(): void
    {
        $this->expectException(MailerException::class);

        $this->mail(toEmail: "to@example.com\nBcc: x@example.com");
    }

    public function testLineBreakInTheValueOfACustomHeaderIsRejected(): void
    {
        $mail = $this->mail();

        $this->expectException(MailerException::class);

        $mail->addCustomHeader(name: 'X-A', value: "v\r\nBcc: e@example.com", maxLineLength: 998);
    }

    public function testLineBreakInTheNameOfACustomHeaderIsRejected(): void
    {
        $mail = $this->mail();

        $this->expectException(MailerException::class);

        $mail->addCustomHeader(name: "X-A\r\nBcc: e@example.com", value: 'v', maxLineLength: 998);
    }

    public function testLineBreaksInAttachmentNamesAreRemoved(): void
    {
        $mail = $this->mail();
        $mail->addAttachment(
            mailerAttachment: new MailerStringAttachment(
                contentString: 'x',
                fileName: "a\r\nBcc: e@example.com.txt",
                type: '',
            ),
        );

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString('name="aBcc: e@example.com.txt"', $message->body);
        $this->assertStringNotContainsString("\r\nBcc:", $message->body);
    }

    public function testLineBreakInTheTypeOfAnAttachmentIsRejected(): void
    {
        $this->expectException(MailerException::class);

        new MailerStringAttachment(contentString: 'x', fileName: 'a.txt', type: "text/plain\r\nBcc: e@example.com");
    }

    public function testParametersInTheTypeOfAnAttachmentAreRejected(): void
    {
        $this->expectException(MailerException::class);

        new MailerStringAttachment(contentString: 'x', fileName: 'a.txt', type: 'text/plain; charset=utf-8');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHeaderNameProvider(): iterable
    {
        yield 'colon' => ['X-A: b'];
        yield 'space' => ['X A'];
        yield 'empty' => [''];
        yield 'umlaut' => ['X-Ä'];
        yield 'tab' => ["X\tA"];
        yield 'line break inside' => ["X-A\nBcc"];
    }

    #[DataProvider('invalidHeaderNameProvider')]
    public function testInvalidNameOfACustomHeaderIsRejected(string $name): void
    {
        $mail = $this->mail();

        $this->expectException(MailerException::class);

        $mail->addCustomHeader(name: $name, value: 'v', maxLineLength: 998);
    }

    public function testCustomHeaderWithSpecialCharactersIsEncoded(): void
    {
        $mail = $this->mail();
        $mail->addCustomHeader(name: 'X-Reference', value: 'Wert Ü', maxLineLength: 998);

        $message = CapturingMailer::capture(mail: $mail);

        $this->assertStringContainsString("\r\nX-Reference: =?utf-8?Q?Wert_=C3=9C?=\r\n", $message->header);
    }

    public function testExceptionsDoNotRepeatTheValues(): void
    {
        try {
            $this->mail()->addCustomHeader(
                name: 'X-A',
                value: "secret-token\r\nBcc: e@example.com",
                maxLineLength: 998,
            );
            self::fail('The header must be rejected.');
        } catch (MailerException $exception) {
            $this->assertStringNotContainsString('secret-token', $exception->getMessage());
        }

        try {
            $this->mail(toEmail: "person@example.com\r\nBcc: e@example.com");
            self::fail('The address must be rejected.');
        } catch (MailerException $exception) {
            $this->assertStringNotContainsString('person', $exception->getMessage());
        }
    }

    public function testHeaderIsTrimmedAndRejectsAnEmptyName(): void
    {
        $this->assertSame("X-A: v\r\n", MailerHeader::createRaw(name: ' X-A ', value: ' v '));

        $this->expectException(MailerException::class);

        MailerHeader::createRaw(name: ' ', value: 'v');
    }

    public function testHeaderRejectsLineBreaks(): void
    {
        $this->expectException(MailerException::class);

        MailerHeader::createRaw(name: 'X-A', value: "a\nb");
    }

    private function mail(
        string $subject = 'Subject',
        string $fromName = 'From',
        string $fromEmail = 'from@example.com',
        string $toName = 'To',
        string $toEmail = 'to@example.com',
    ): TextMail {
        return new TextMail(
            senderEmail: 'send@example.com',
            fromEmail: $fromEmail,
            fromName: $fromName,
            toEmail: $toEmail,
            toName: $toName,
            subject: $subject,
            textBody: 'Hello',
        );
    }
}
