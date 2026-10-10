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
use actra\yuf\mailer\FixedServerNameResolver;
use actra\yuf\mailer\MailMimeBody;
use actra\yuf\mailer\MailMimeHeader;
use DateTimeImmutable;
use Override;

/**
 * The mailer double recommended for projects (docs/testing.md): records the sent mails instead of delivering them.
 * Uses only the public API of the mailer, like a double outside of yuf.
 */
final class RecordingMailer extends AbstractMailer
{
    /** @var list<AbstractMail> */
    public private(set) array $sentMails = [];

    public function __construct()
    {
        parent::__construct(
            serverAddress: '192.0.2.1',
            clock: new FixedClock(now: new DateTimeImmutable(datetime: '2026-01-02 03:04:05')),
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
        return 998;
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
