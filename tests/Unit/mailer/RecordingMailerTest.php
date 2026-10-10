<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\mailer\AbstractMail;
use actra\yuf\mailer\MailerAddress;
use actra\yuf\mailer\MailerAddressKindEnum;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\RecordingMailer;
use PHPUnit\Framework\TestCase;

/**
 * A mailer double of a project records the `AbstractMail` and asserts it with the public getters, without parsing
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
