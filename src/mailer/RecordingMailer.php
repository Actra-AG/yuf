<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use Override;

/**
 * Mail transport for tests: records the sent mails instead of delivering them, without network or DNS. Assert them
 * with the getters of `AbstractMail` (docs/mail.md). Behaves like an SMTP mailer when the message is built (`To` and
 * `Subject` in the header, no `Bcc`, lines up to 998 characters).
 */
final class RecordingMailer extends AbstractMailer
{
    /** @var list<AbstractMail> */
    public private(set) array $sentMails = [];

    public function __construct(Clock $clock = new SystemClock())
    {
        parent::__construct(
            serverAddress: '192.0.2.1',
            clock: $clock,
            serverNameResolver: new FixedServerNameResolver(),
        );
    }

    #[Override]
    public function headerHasTo(): bool
    {
        return true;
    }

    #[Override]
    public function headerHasSubject(): bool
    {
        return true;
    }

    #[Override]
    public function headerHasBcc(): bool
    {
        return false;
    }

    #[Override]
    public function getMaxLineLength(): int
    {
        return MailerConstants::MAX_LINE_LENGTH;
    }

    #[Override]
    public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $this->sentMails[] = $abstractMail;
    }
}
