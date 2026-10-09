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
 * The body of a message: the single part or the multipart structure with alternative text, inline images and
 * attachments.
 *
 * @internal Created by `AbstractMail`; a mailer only reads `getMimeBody()`
 */
final readonly class MailMimeBody
{
    private const string PREAMBLE = 'This is a multi-part message in MIME format.';

    private string $body;

    public function __construct(
        private int $maxLineLength,
        private MailerCharsetEnum $charSet,
        private MailerContentTypeEnum $contentType,
        MailerEncodingEnum $encoding,
        MailerMessageTypeEnum $messageType,
        string $rawBody,
        string $alternativeBody,
        string $boundary1,
        string $boundary2,
        string $boundary3,
        private MailerAttachmentCollection $mailerAttachmentCollection,
    ) {
        $html = MailMimePart::create(
            charSet: $charSet,
            // With an alternative text the message type is multipart/alternative, the HTML part is text/html
            contentType: $messageType->hasAlternative() ? MailerContentTypeEnum::TEXT_HTML : $contentType,
            encoding: $encoding,
            content: $rawBody,
        );
        $text = MailMimePart::create(
            charSet: $charSet,
            contentType: MailerContentTypeEnum::TEXT_PLAIN,
            encoding: $encoding,
            content: $alternativeBody,
        );
        $this->body = match ($messageType) {
            MailerMessageTypeEnum::PLAIN => MailerContentEncoder::encode(string: $rawBody, encoding: $encoding),
            MailerMessageTypeEnum::INLINE => $this->preamble()
                . $html->render(boundary: $boundary1)
                . $this->attachments(inline: true, boundary: $boundary1),
            MailerMessageTypeEnum::ATTACH => $this->preamble()
                . $html->render(boundary: $boundary1)
                . $this->attachments(inline: false, boundary: $boundary1),
            MailerMessageTypeEnum::INLINE_ATTACH => $this->preamble()
                . $this->relatedPart(body: $html, boundary: $boundary1, relatedBoundary: $boundary2)
                . MailerConstants::CRLF
                . $this->attachments(inline: false, boundary: $boundary1),
            MailerMessageTypeEnum::ALT => $this->preamble()
                . $text->render(boundary: $boundary1)
                . $html->render(boundary: $boundary1)
                . $this->endBoundary(boundary: $boundary1),
            MailerMessageTypeEnum::ALT_INLINE => $this->preamble()
                . $this->alternativeWithRelated(
                    text: $text,
                    html: $html,
                    boundary: $boundary1,
                    relatedBoundary: $boundary2,
                ),
            MailerMessageTypeEnum::ALT_ATTACH => $this->preamble()
                . $this->boundaryLine(boundary: $boundary1)
                . MailerHeader::createRaw(name: 'Content-Type', value: 'multipart/alternative;')
                . ' boundary="' . $boundary2 . '"' . MailerConstants::CRLF
                . MailerConstants::CRLF
                . $text->render(boundary: $boundary2)
                . $html->render(boundary: $boundary2)
                . $this->endBoundary(boundary: $boundary2)
                . MailerConstants::CRLF
                . $this->attachments(inline: false, boundary: $boundary1),
            MailerMessageTypeEnum::ALT_INLINE_ATTACH => $this->preamble()
                . $this->boundaryLine(boundary: $boundary1)
                . MailerHeader::createRaw(name: 'Content-Type', value: 'multipart/alternative;')
                . ' boundary="' . $boundary2 . '"' . MailerConstants::CRLF
                . MailerConstants::CRLF
                . $this->alternativeWithRelated(
                    text: $text,
                    html: $html,
                    boundary: $boundary2,
                    relatedBoundary: $boundary3,
                )
                . MailerConstants::CRLF
                . $this->attachments(inline: false, boundary: $boundary1),
        };
    }

    public function getMimeBody(): string
    {
        return $this->body;
    }

    private function preamble(): string
    {
        return MailMimeBody::PREAMBLE . MailerConstants::CRLF . MailerConstants::CRLF;
    }

    private function boundaryLine(string $boundary): string
    {
        return '--' . $boundary . MailerConstants::CRLF;
    }

    private function endBoundary(string $boundary): string
    {
        return MailerConstants::CRLF . '--' . $boundary . '--' . MailerConstants::CRLF;
    }

    /**
     * The HTML part and its inline images as multipart/related inside the part of the boundary.
     */
    private function relatedPart(MailMimePart $body, string $boundary, string $relatedBoundary): string
    {
        return $this->boundaryLine(boundary: $boundary)
            . MailerHeader::createRaw(
                name: 'Content-Type',
                value: MailerContentTypeEnum::MULTIPART_RELATED->value . ';',
            )
            . ' boundary="' . $relatedBoundary . '";' . MailerConstants::CRLF
            . ' type="' . MailerContentTypeEnum::TEXT_HTML->value . '"' . MailerConstants::CRLF
            . MailerConstants::CRLF
            . $body->render(boundary: $relatedBoundary)
            . $this->attachments(inline: true, boundary: $relatedBoundary);
    }

    private function alternativeWithRelated(
        MailMimePart $text,
        MailMimePart $html,
        string $boundary,
        string $relatedBoundary,
    ): string {
        return $text->render(boundary: $boundary)
            . $this->relatedPart(body: $html, boundary: $boundary, relatedBoundary: $relatedBoundary)
            . MailerConstants::CRLF
            . $this->endBoundary(boundary: $boundary);
    }

    /**
     * The attachments of one disposition as parts of the boundary, followed by the closing delimiter.
     */
    private function attachments(bool $inline, string $boundary): string
    {
        $parts = '';
        foreach ($this->mailerAttachmentCollection->list() as $attachment) {
            if ($attachment->dispositionInline !== $inline) {
                continue;
            }
            $parts .= $this->attachment(attachment: $attachment, boundary: $boundary);
        }

        return $parts . '--' . $boundary . '--' . MailerConstants::CRLF;
    }

    private function attachment(MailerAttachment $attachment, string $boundary): string
    {
        $encodedName = MailerHeaderEncoder::encodeText(
            string: MailerHeaderEncoder::secure(string: $attachment->fileName),
            maxLineLength: $this->maxLineLength,
            defaultCharSet: $this->charSet,
        );
        $part = $this->boundaryLine(boundary: $boundary)
            . 'Content-Type: ' . $attachment->type . '; name=' . $this->quotedString(string: $encodedName)
            . MailerConstants::CRLF;
        // RFC 1341 part 5: 7bit is assumed if not specified
        if ($attachment->encoding !== MailerEncodingEnum::SEVEN_BIT) {
            $part .= 'Content-Transfer-Encoding: ' . $attachment->encoding->value . MailerConstants::CRLF;
        }
        // Only inline attachments have a Content-ID
        if ($attachment->dispositionInline) {
            $part .= 'Content-ID: <' . $encodedName . '>' . MailerConstants::CRLF;
        }

        return $part
            . 'Content-Disposition: ' . ($attachment->dispositionInline ? 'inline' : 'attachment')
            . '; filename=' . $this->quotedString(string: $encodedName) . MailerConstants::CRLF
            . MailerConstants::CRLF
            . MailerContentEncoder::encode(string: $attachment->getContent(), encoding: $attachment->encoding)
            . MailerConstants::CRLF;
    }

    private function quotedString(string $string): string
    {
        if (preg_match(pattern: '/[ ()<>@,;:"\/\[\]?=]/', subject: $string) !== 1) {
            return $string;
        }

        // The string contains characters that need quoting: double quote it and escape the double quotes
        return '"' . str_replace(search: '"', replace: '\\"', subject: $string) . '"';
    }
}
