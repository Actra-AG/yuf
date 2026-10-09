<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\mailer;

use actra\yuf\mailer\AbstractMail;
use actra\yuf\mailer\AbstractMailer;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\MailMimeBody;
use actra\yuf\mailer\MailMimeHeader;
use Override;

/**
 * A mailer whose server is not reachable: every delivery throws a `MailerException`.
 */
final class FailingMailer extends AbstractMailer
{
    public function __construct()
    {
        parent::__construct(serverAddress: '192.0.2.1', serverNameResolver: new FixedServerNameResolver());
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
        throw new MailerException(message: 'Connection to the mail server failed');
    }
}
