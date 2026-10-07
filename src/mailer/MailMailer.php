<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use Override;

class MailMailer extends AbstractMailer
{
    #[Override]
    public function headerHasTo(): bool
    {
        return false;
    }

    #[Override]
    public function headerHasSubject(): bool
    {
        return false;
    }

    #[Override]
    public function sendMail(
        AbstractMail $abstractMail,
        MailMimeHeader $mailMimeHeader,
        MailMimeBody $mailMimeBody,
    ): void {
        $senderEmail = $abstractMail->sender->getPunyEncodedEmail();
        $result = mail(
            to: $abstractMail->mailerAddressCollection->listAsCommaSeparatedString(
                mailerAddressKindEnum: MailerAddressKindEnum::KIND_TO,
                maxLineLength: $this->getMaxLineLength(),
                defaultCharSet: $abstractMail->charSet,
            ),
            subject: $abstractMail->getSubjectForHeader(maxLineLength: $this->getMaxLineLength()),
            message: $mailMimeBody->getMimeBody(),
            additional_headers: $mailMimeHeader->getMimeHeader() . MailerConstants::CRLF . MailerConstants::CRLF,
            additional_params: MailerFunctions::isShellSafe(string: $senderEmail) ? '-f' . $senderEmail : '',
        );
        if ($result === false) {
            throw new MailerException(message: 'Could not instantiate mail function.');
        }
    }

    #[Override]
    public function getMaxLineLength(): int
    {
        return MailerConstants::MAIL_MAX_LINE_LENGTH;
    }
}
