<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\clock\FixedClock;
use actra\yuf\mailer\AbstractMail;
use actra\yuf\mailer\AbstractMailer;
use actra\yuf\mailer\MailMimeBody;
use actra\yuf\mailer\MailMimeHeader;
use DateTimeImmutable;
use LogicException;
use Override;

/**
 * Sends nothing: remembers the header and the body of the message it is asked to send. The date is
 * 2026-10-08 12:00:00 UTC, the unique id is `ID` and the server name `mail.example.com`.
 */
final class CapturingMailer extends AbstractMailer
{
    public private(set) ?CapturedMessage $message = null;

    /**
     * @param bool $isSmtpLike like an SMTP mailer (header `To` and `Subject`, no `Bcc`, long lines) or like `mail()`
     */
    public function __construct(private readonly bool $isSmtpLike = true)
    {
        parent::__construct(
            serverAddress: '192.0.2.1',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-10-08 12:00:00 UTC')),
            mimeIdGenerator: new FixedMimeIdGenerator(),
            serverNameResolver: new FixedServerNameResolver(),
        );
    }

    #[Override]
    public function headerHasTo(): bool
    {
        return $this->isSmtpLike;
    }

    #[Override]
    public function headerHasSubject(): bool
    {
        return $this->isSmtpLike;
    }

    #[Override]
    public function headerHasBcc(): bool
    {
        return !$this->isSmtpLike;
    }

    #[Override]
    public function getMaxLineLength(): int
    {
        return $this->isSmtpLike ? 998 : 63;
    }

    #[Override]
    public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $this->message = new CapturedMessage(
            header: $mailMimeHeader->getMimeHeader(),
            body: $mailMimeBody->getMimeBody(),
        );
    }

    public static function capture(AbstractMail $mail, bool $isSmtpLike = true): CapturedMessage
    {
        $mailer = new CapturingMailer(isSmtpLike: $isSmtpLike);
        $mail->send(abstractMailer: $mailer);

        return $mailer->message ?? throw new LogicException(message: 'The mailer was not called.');
    }
}
