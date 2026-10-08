<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
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
 * @license   http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 * @note      This program is distributed in the hope that it will be useful - WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or
 * FITNESS FOR A PARTICULAR PURPOSE.
 */

namespace actra\yuf\mailer;

/**
 * One text part of a multipart message with its own charset and transfer encoding.
 *
 * @internal
 */
final readonly class MailMimePart
{
    private function __construct(
        private MailerCharsetEnum $charSet,
        private MailerContentTypeEnum $contentType,
        private MailerEncodingEnum $encoding,
        private string $content,
    ) {}

    /**
     * Chooses charset and encoding of the part: an 8bit encoding without 8bit characters is downgraded to 7bit and
     * ASCII, and a part with a line longer than the maximum is quoted-printable (unless it is base64).
     */
    public static function create(
        MailerCharsetEnum $charSet,
        MailerContentTypeEnum $contentType,
        MailerEncodingEnum $encoding,
        string $content,
    ): MailMimePart {
        $partEncoding = $encoding;
        $partCharSet = $charSet;
        // All ISO 8859, Windows code pages and UTF-8 are ASCII compatible up to 7bit
        if ($encoding === MailerEncodingEnum::EIGHT_BIT && !MailerContentEncoder::has8bitChars(text: $content)) {
            $partEncoding = MailerEncodingEnum::SEVEN_BIT;
            $partCharSet = MailerCharsetEnum::ASCII;
        }
        // Lines that are too long need an encoding that shortens them
        if (
            $encoding !== MailerEncodingEnum::BASE64
            && MailerContentEncoder::hasLineLongerThanMaximum(text: $content)
        ) {
            $partEncoding = MailerEncodingEnum::QUOTED_PRINTABLE;
        }

        return new MailMimePart(
            charSet: $partCharSet,
            contentType: $contentType,
            encoding: $partEncoding,
            content: $content,
        );
    }

    public function render(string $boundary): string
    {
        $result = '--' . $boundary . MailerConstants::CRLF
            . 'Content-Type: ' . $this->contentType->value . '; charset=' . $this->charSet->value
            . MailerConstants::CRLF;
        // RFC 1341 part 5: 7bit is assumed if not specified
        if ($this->encoding !== MailerEncodingEnum::SEVEN_BIT) {
            $result .= MailerHeader::createRaw(name: 'Content-Transfer-Encoding', value: $this->encoding->value);
        }

        return $result
            . MailerConstants::CRLF
            . MailerContentEncoder::encode(string: $this->content, encoding: $this->encoding)
            . MailerConstants::CRLF;
    }
}
