<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\RecordingMailer;
use actra\yuf\mailer\SentMail;
use actra\yuf\mailer\SentMailListener;
use actra\yuf\mailer\TextMail;
use actra\yuf\tests\Double\mailer\FailingMailer;
use actra\yuf\tests\Double\mailer\RecordingSentMailListener;
use DateTimeImmutable;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Applications log sent mails (time, recipients, subject) through a listener of the mailer.
 */
final class SentMailListenerTest extends TestCase
{
    public function testTellsTheListenersAboutASentMail(): void
    {
        $now = new DateTimeImmutable(datetime: '2026-10-31 12:34:56');
        $mailer = new RecordingMailer(clock: new FixedClock(now: $now));
        $firstListener = new RecordingSentMailListener();
        $secondListener = new RecordingSentMailListener();
        $mailer->addSentMailListener(sentMailListener: $firstListener);
        $mailer->addSentMailListener(sentMailListener: $secondListener);
        $mail = $this->createMail();
        $mail->addTo(inputEmail: 'second@example.com');
        $mail->addCc(inputEmail: 'Copy@Example.com', inputName: 'Copy');
        $mail->addBcc(inputEmail: 'blind@bücher.example');
        $mail->addReplyTo(inputEmail: 'support@example.com');

        $mail->send(abstractMailer: $mailer);

        $this->assertCount(1, $firstListener->sentMails);
        $this->assertSame($firstListener->sentMails, $secondListener->sentMails);
        $sentMail = $firstListener->sentMails[0];
        $this->assertEquals($now, $sentMail->sentAt);
        $this->assertSame('from@example.com', $sentMail->fromEmail);
        $this->assertSame(['customer@example.com', 'second@example.com'], $sentMail->toEmails);
        $this->assertSame(['copy@example.com'], $sentMail->ccEmails);
        $this->assertSame(['blind@xn--bcher-kva.example'], $sentMail->bccEmails);
        $this->assertSame('Ihre Bestellung', $sentMail->subject);
    }

    public function testDoesNotTellTheListenersAboutAFailedDelivery(): void
    {
        $mailer = new FailingMailer();
        $listener = new RecordingSentMailListener();
        $mailer->addSentMailListener(sentMailListener: $listener);

        try {
            $this->createMail()->send(abstractMailer: $mailer);
            self::fail('The delivery must fail.');
        } catch (MailerException) {
        }

        $this->assertSame([], $listener->sentMails);
    }

    public function testSendsWithoutListener(): void
    {
        $mailer = new RecordingMailer();
        $mail = $this->createMail();

        $mail->send(abstractMailer: $mailer);

        $this->assertSame([$mail], $mailer->sentMails);
    }

    public function testAnExceptionOfTheListenerPropagatesButTheMailCountsAsSent(): void
    {
        $mailer = new RecordingMailer();
        $mailer->addSentMailListener(
            sentMailListener: new class implements SentMailListener {
                #[Override]
                public function onMailSent(SentMail $sentMail): void
                {
                    throw new LogicException(message: 'Log table is missing');
                }
            },
        );
        $mail = $this->createMail();

        try {
            $mail->send(abstractMailer: $mailer);
            self::fail('The exception of the listener must propagate.');
        } catch (LogicException $logicException) {
            $this->assertSame('Log table is missing', $logicException->getMessage());
        }

        $this->assertSame([$mail], $mailer->sentMails);
        $this->expectExceptionObject(new MailerException(message: 'You cannot send the same email multiple times.'));
        $mail->send(abstractMailer: $mailer);
    }

    private function createMail(): TextMail
    {
        return new TextMail(
            senderEmail: 'sender@example.com',
            fromEmail: 'from@example.com',
            fromName: 'Shop',
            toEmail: 'Customer@Example.com',
            toName: 'Customer',
            subject: ' Ihre Bestellung ',
            textBody: 'Vielen Dank.',
        );
    }
}
