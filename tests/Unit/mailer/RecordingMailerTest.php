<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\AbstractMail;
use actra\yuf\mailer\MailerAddress;
use actra\yuf\mailer\MailerAddressKindEnum;
use actra\yuf\mailer\RecordingMailer;
use actra\yuf\mailer\TextMail;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The test mailer of projects records the `AbstractMail` and asserts it with the public getters, without parsing
 * headers (docs/testing.md).
 */
final class RecordingMailerTest extends TestCase
{
    public function testRecordsTheSentMail(): void
    {
        $mail = new TextMail(
            senderEmail: 'sender@example.com',
            fromEmail: 'from@example.com',
            fromName: 'Shop',
            toEmail: 'Customer@Example.com',
            toName: 'Customer',
            subject: ' Ihre Bestellung ',
            textBody: " Vielen Dank.\n",
        );
        $mail->addReplyTo(inputEmail: 'support@example.com');
        $mailer = new RecordingMailer();

        $mail->send(abstractMailer: $mailer);

        $this->assertSame([$mail], $mailer->sentMails);
        $sentMail = $mailer->sentMails[0];
        $this->assertSame('Ihre Bestellung', $sentMail->getSubject());
        $this->assertSame('Vielen Dank.', $sentMail->getBody());
        $this->assertSame('from@example.com', $sentMail->fromAddress->getPunyEncodedEmail());
        $this->assertSame(['customer@example.com'], $this->listEmails(mail: $sentMail, kind: MailerAddressKindEnum::KIND_TO));
        $this->assertSame(
            ['support@example.com'],
            $this->listEmails(mail: $sentMail, kind: MailerAddressKindEnum::KIND_REPLY_TO),
        );
    }

    public function testRecordsEveryMailInOrderWithoutNetwork(): void
    {
        $mailer = new RecordingMailer();
        $first = $this->createMail(subject: 'First');
        $second = $this->createMail(subject: 'Second');

        $first->send(abstractMailer: $mailer);
        $second->send(abstractMailer: $mailer);

        $this->assertSame([$first, $second], $mailer->sentMails);
        $this->assertSame('mail.example.com', $mailer->getServerName());
    }

    public function testBuildsTheMessageLikeAnSmtpMailer(): void
    {
        $mailer = new RecordingMailer();

        $this->assertTrue($mailer->headerHasTo());
        $this->assertTrue($mailer->headerHasSubject());
        $this->assertFalse($mailer->headerHasBcc());
        $this->assertSame(998, $mailer->getMaxLineLength());
    }

    public function testUsesTheGivenClock(): void
    {
        $now = new DateTimeImmutable(datetime: '2026-01-02 03:04:05');

        $this->assertSame($now, new RecordingMailer(clock: new FixedClock(now: $now))->getClock()->now());
    }

    private function createMail(string $subject): TextMail
    {
        return new TextMail(
            senderEmail: 'sender@example.com',
            fromEmail: 'from@example.com',
            fromName: 'Shop',
            toEmail: 'customer@example.com',
            toName: 'Customer',
            subject: $subject,
            textBody: 'Text',
        );
    }

    /**
     * @return list<string>
     */
    private function listEmails(AbstractMail $mail, MailerAddressKindEnum $kind): array
    {
        return array_map(
            callback: static fn(MailerAddress $address): string => $address->getPunyEncodedEmail(),
            array: $mail->mailerAddressCollection->list(mailerAddressKindEnum: $kind),
        );
    }
}
