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

/**
 * The header of a message (all header lines, without the blank line before the body).
 *
 * @internal Created by `AbstractMail`; a mailer only reads `getMimeHeader()`
 */
final class MailMimeHeader
{
    /** @var list<string> */
    private array $headerItems = [];

    public function __construct(
        AbstractMailer $abstractMailer,
        string $subjectForHeader,
        MailerAddress $fromAddress,
        MailerAddressCollection $mailerAddressCollection,
        string $uniqueId,
        MailerPriorityEnum $priority,
        ?MailerAddress $confirmReadingToAddress,
        MailerHeaderCollection $customHeaders,
        MailerMessageTypeEnum $messageType,
        MailerContentTypeEnum $contentType,
        MailerCharsetEnum $charSet,
        MailerEncodingEnum $encoding,
        string $boundary1,
    ) {
        $this->addDateAndAddresses(
            abstractMailer: $abstractMailer,
            fromAddress: $fromAddress,
            mailerAddressCollection: $mailerAddressCollection,
            charSet: $charSet,
        );
        if ($abstractMailer->headerHasSubject()) {
            $this->addHeaderItemIfNotEmpty(item: MailerHeader::createRaw(name: 'Subject', value: $subjectForHeader));
        }
        $this->addHeaderItemIfNotEmpty(
            item: MailerHeader::createRaw(
                name: 'Message-ID',
                value: '<' . $uniqueId . '@' . $abstractMailer->getServerName() . '>',
            ),
        );
        $this->addHeaderItemIfNotEmpty(
            item: MailerHeader::createRaw(name: 'X-Mailer', value: 'PHP/' . phpversion()),
        );
        $this->addHeaderItemIfNotEmpty(
            item: MailerHeader::createRaw(name: 'X-Priority', value: (string) $priority->value),
        );
        if ($confirmReadingToAddress !== null) {
            $this->addHeaderItemIfNotEmpty(
                item: MailerHeader::createRaw(
                    name: 'Disposition-Notification-To',
                    value: $confirmReadingToAddress->getFormattedAddressForMailer(
                        maxLineLength: $abstractMailer->getMaxLineLength(),
                        defaultCharSet: $charSet,
                    ),
                ),
            );
        }
        foreach ($customHeaders->list() as $mailerHeader) {
            $this->addHeaderItemIfNotEmpty(item: $mailerHeader->get());
        }
        $this->addHeaderItemIfNotEmpty(item: MailerHeader::createRaw(name: 'MIME-Version', value: '1.0'));
        $this->addHeaderItemIfNotEmpty(
            item: $this->getMailMime(
                messageType: $messageType,
                contentType: $contentType,
                charSet: $charSet,
                encoding: $encoding,
                boundary1: $boundary1,
            ),
        );
    }

    public function getMimeHeader(): string
    {
        return rtrim(
            string: implode(separator: '', array: $this->headerItems),
            characters: " \r\n\t",
        );
    }

    private function addDateAndAddresses(
        AbstractMailer $abstractMailer,
        MailerAddress $fromAddress,
        MailerAddressCollection $mailerAddressCollection,
        MailerCharsetEnum $charSet,
    ): void {
        $maxLineLength = $abstractMailer->getMaxLineLength();
        $formattedFromAddress = $fromAddress->getFormattedAddressForMailer(
            maxLineLength: $maxLineLength,
            defaultCharSet: $charSet,
        );
        $this->addHeaderItemIfNotEmpty(
            item: MailerHeader::createRaw(
                name: 'Date',
                value: $abstractMailer->getClock()->now()->format(format: 'r'),
            ),
        );
        $this->addHeaderItemIfNotEmpty(
            item: MailerHeader::createRaw(name: MailerAddressKindEnum::KIND_FROM->value, value: $formattedFromAddress),
        );
        $kinds = [MailerAddressKindEnum::KIND_CC];
        if ($abstractMailer->headerHasTo()) {
            array_unshift($kinds, MailerAddressKindEnum::KIND_TO);
        }
        if ($abstractMailer->headerHasBcc()) {
            $kinds[] = MailerAddressKindEnum::KIND_BCC;
        }
        foreach ($kinds as $kind) {
            $this->addHeaderItemIfNotEmpty(
                item: $mailerAddressCollection->getHeaderString(
                    mailerAddressKindEnum: $kind,
                    maxLineLength: $maxLineLength,
                    defaultCharSet: $charSet,
                ),
            );
        }
        // Replies go to the sender if no reply address is given
        $this->addHeaderItemIfNotEmpty(
            item: $mailerAddressCollection->has(mailerAddressKindEnum: MailerAddressKindEnum::KIND_REPLY_TO)
                ? $mailerAddressCollection->getHeaderString(
                    mailerAddressKindEnum: MailerAddressKindEnum::KIND_REPLY_TO,
                    maxLineLength: $maxLineLength,
                    defaultCharSet: $charSet,
                )
                : MailerHeader::createRaw(
                    name: MailerAddressKindEnum::KIND_REPLY_TO->value,
                    value: $formattedFromAddress,
                ),
        );
    }

    private function addHeaderItemIfNotEmpty(string $item): void
    {
        if ($item === '') {
            return;
        }
        $this->headerItems[] = $item;
    }

    private function getMailMime(
        MailerMessageTypeEnum $messageType,
        MailerContentTypeEnum $contentType,
        MailerCharsetEnum $charSet,
        MailerEncodingEnum $encoding,
        string $boundary1,
    ): string {
        $multipartContentType = $messageType->multipartContentType();
        $result = $multipartContentType === null
            ? MailerHeader::createRaw(
                name: 'Content-Type',
                value: $contentType->value . '; charset=' . $charSet->value,
            )
            : MailerHeader::createRaw(name: 'Content-Type', value: $multipartContentType->value . ';')
            . ' boundary="' . $boundary1 . '"' . MailerConstants::CRLF;

        // RFC 1341 part 5: 7bit is assumed if not specified
        if ($encoding === MailerEncodingEnum::SEVEN_BIT) {
            return $result;
        }
        // RFC 2045 section 6.4: multipart messages may only use 7bit, 8bit or binary. Quoted-printable and base64 are
        // 7bit compatible and need no header for the multipart message itself
        if ($messageType->isMultipart() && $encoding !== MailerEncodingEnum::EIGHT_BIT) {
            return $result;
        }

        return $result . MailerHeader::createRaw(name: 'Content-Transfer-Encoding', value: $encoding->value);
    }
}
