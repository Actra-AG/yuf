<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   LGPL-2.1-only
 */

declare(strict_types=1);
/**
 * Derived work from PHPMailer, reduced to the code needed by this Framework.
 * For the original full library, please see:
 *
 * @see       https://github.com/PHPMailer/PHPMailer/ The PHPMailer GitHub project
 * @author    Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author    Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author    Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author    Brent R. Matzelle (original founder)
 * @author    Actra AG (for derived, reduced code)  - www.actra.ch
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @copyright 2022 Actra AG
 * @license   https://www.gnu.org/licenses/old-licenses/lgpl-2.1.html GNU Lesser General Public License, version 2.1
 *            only (LGPL-2.1-only, as PHPMailer), see the file LICENSE in src/mailer/. Changed by Actra AG: reduced,
 *            split into classes and adapted to the yuf coding standard; see the Git history of yuf for details.
 * @note      This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

namespace actra\yuf\mailer;

use actra\yuf\mailer\attachment\MailerAttachment;
use actra\yuf\mailer\attachment\MailerAttachmentCollection;

/**
 * Extension point: a mail of a project extends it, sets the body with `setTextBody()` or `setHtmlBody()` and is sent
 * with `send()`. `TextMail` and `HtmlMail` are the ready-made mails.
 *
 * Every string that comes from outside (addresses, names, subject, file names) is checked or stripped of line breaks
 * before it goes into a header.
 */
abstract class AbstractMail
{
    public readonly MailerAddress $sender;
    public readonly MailerAddress $fromAddress;
    public readonly MailerAddressCollection $mailerAddressCollection;
    public int $wordWrap = 0;
    private bool $isSent = false;
    private ?MailerAddress $confirmReadingToAddress = null;
    private string $body = '';
    private string $alternativeBody = '';
    private bool $isHtmlBody = false;
    private readonly MailerAttachmentCollection $mailerAttachmentCollection;
    private readonly MailerHeaderCollection $customHeaders;
    private readonly string $subject;

    protected function __construct(
        string $senderEmail,
        string $fromEmail,
        string $fromName,
        string $toEmail,
        string $toName,
        string $subject,
        public readonly MailerCharsetEnum $charSet = MailerCharsetEnum::UTF8,
        private readonly MailerEncodingEnum $encoding = MailerEncodingEnum::QUOTED_PRINTABLE,
        private readonly MailerPriorityEnum $priority = MailerPriorityEnum::NORMAL,
    ) {
        $this->sender = MailerAddress::createSenderAddress(inputEmail: $senderEmail, inputName: '');
        $this->fromAddress = MailerAddress::createFromAddress(inputEmail: $fromEmail, inputName: $fromName);
        $this->mailerAddressCollection = new MailerAddressCollection();
        $this->mailerAttachmentCollection = new MailerAttachmentCollection();
        $this->customHeaders = new MailerHeaderCollection();
        $this->addTo(inputEmail: $toEmail, inputName: $toName);
        $this->subject = trim(string: $subject);
    }

    public function addTo(string $inputEmail, string $inputName = ''): void
    {
        $this->mailerAddressCollection->addItem(
            mailerAddress: MailerAddress::createToAddress(inputEmail: $inputEmail, inputName: $inputName),
        );
    }

    public function addReplyTo(string $inputEmail, string $inputName = ''): void
    {
        $this->mailerAddressCollection->addItem(
            mailerAddress: MailerAddress::createReplyToAddress(inputEmail: $inputEmail, inputName: $inputName),
        );
    }

    public function addCc(string $inputEmail, string $inputName = ''): void
    {
        $this->mailerAddressCollection->addItem(
            mailerAddress: MailerAddress::createCcAddress(inputEmail: $inputEmail, inputName: $inputName),
        );
    }

    public function addBcc(string $inputEmail, string $inputName = ''): void
    {
        $this->mailerAddressCollection->addItem(
            mailerAddress: MailerAddress::createBccAddress(inputEmail: $inputEmail, inputName: $inputName),
        );
    }

    public function setConfirmReadingToAddress(string $inputEmail, string $inputName = ''): void
    {
        $this->confirmReadingToAddress = MailerAddress::createConfirmReadingToAddress(
            inputEmail: $inputEmail,
            inputName: $inputName,
        );
    }

    public function addAttachment(MailerAttachment $mailerAttachment): void
    {
        $this->mailerAttachmentCollection->addItem(mailerAttachment: $mailerAttachment);
    }

    /**
     * @param int $maxLineLength line length of the encoded words of a value with special characters
     */
    public function addCustomHeader(
        string $name,
        string $value,
        int $maxLineLength,
    ): void {
        $this->customHeaders->addItem(
            mailerHeader: MailerHeader::createEncodedHeaderText(
                name: $name,
                value: $value,
                maxLineLength: $maxLineLength,
                defaultCharSet: $this->charSet,
            ),
        );
    }

    public function send(AbstractMailer $abstractMailer): void
    {
        if ($this->isSent) {
            throw new MailerException(message: 'You cannot send the same email multiple times.');
        }
        if ($this->body === '') {
            throw new MailerException(message: 'Message body is empty');
        }

        $messageType = MailerMessageTypeEnum::fromParts(
            hasAlternative: $this->alternativeBody !== '',
            hasInlineImages: $this->mailerAttachmentCollection->hasInlineImages(),
            hasAttachments: $this->mailerAttachmentCollection->hasAttachments(),
        );
        $body = $this->body;
        $alternativeBody = $this->alternativeBody;
        if ($this->wordWrap > 0) {
            if ($messageType->hasAlternative()) {
                $alternativeBody = $this->wrap(text: $alternativeBody);
            } else {
                $body = $this->wrap(text: $body);
            }
        }
        $contentType = match (true) {
            $messageType->hasAlternative() => MailerContentTypeEnum::MULTIPART_ALTERNATIVE,
            $this->isHtmlBody => MailerContentTypeEnum::TEXT_HTML,
            default => MailerContentTypeEnum::TEXT_PLAIN,
        };
        $encoding = $this->encoding;
        // The single part of the message must fit in the lines of a message, the parts of a multipart message are
        // checked one by one
        if (
            $messageType === MailerMessageTypeEnum::PLAIN
            && $encoding !== MailerEncodingEnum::BASE64
            && MailerContentEncoder::hasLineLongerThanMaximum(text: $body)
        ) {
            $encoding = MailerEncodingEnum::QUOTED_PRINTABLE;
        }

        $uniqueId = $abstractMailer->createUniqueId();
        $boundary1 = 'b1_' . $uniqueId;
        $maxLineLength = $abstractMailer->getMaxLineLength();
        $abstractMailer->sendMail(
            abstractMail: $this,
            mailMimeHeader: new MailMimeHeader(
                abstractMailer: $abstractMailer,
                subjectForHeader: $this->getSubjectForHeader(maxLineLength: $maxLineLength),
                fromAddress: $this->fromAddress,
                mailerAddressCollection: $this->mailerAddressCollection,
                uniqueId: $uniqueId,
                priority: $this->priority,
                confirmReadingToAddress: $this->confirmReadingToAddress,
                customHeaders: $this->customHeaders,
                messageType: $messageType,
                contentType: $contentType,
                charSet: $this->charSet,
                encoding: $encoding,
                boundary1: $boundary1,
            ),
            mailMimeBody: new MailMimeBody(
                maxLineLength: $maxLineLength,
                charSet: $this->charSet,
                contentType: $contentType,
                encoding: $encoding,
                messageType: $messageType,
                rawBody: $body,
                alternativeBody: $alternativeBody,
                boundary1: $boundary1,
                boundary2: 'b2_' . $uniqueId,
                boundary3: 'b3_' . $uniqueId,
                mailerAttachmentCollection: $this->mailerAttachmentCollection,
            ),
        );
        $this->isSent = true;
    }

    public function getSubjectForHeader(int $maxLineLength): string
    {
        return MailerHeaderEncoder::encodeText(
            string: MailerHeaderEncoder::secure(string: $this->subject),
            maxLineLength: $maxLineLength,
            defaultCharSet: $this->charSet,
        );
    }

    protected function setTextBody(string $textBody): void
    {
        $this->body = trim(string: $textBody);
    }

    protected function setHtmlBody(string $htmlBody, string $alternativeBody): void
    {
        $this->body = trim(string: $htmlBody);
        $this->alternativeBody = trim(string: $alternativeBody);
        $this->isHtmlBody = true;
    }

    private function wrap(string $text): string
    {
        return MailerTextWrapper::wrap(
            message: $text,
            length: $this->wordWrap,
            charSet: $this->charSet,
            qpMode: false,
        );
    }
}
