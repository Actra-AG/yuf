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
 * Encodes the content of a message part with its content transfer encoding and offers the checks for the choice of
 * the encoding.
 *
 * Static on purpose: pure functions without state.
 *
 * @internal
 */
final readonly class MailerContentEncoder
{
    public static function encode(string $string, MailerEncodingEnum $encoding): string
    {
        return match ($encoding) {
            MailerEncodingEnum::BASE64 => chunk_split(
                string: base64_encode(string: $string),
                length: MailerConstants::STD_LINE_LENGTH,
                separator: MailerConstants::CRLF,
            ),
            MailerEncodingEnum::SEVEN_BIT,
            MailerEncodingEnum::EIGHT_BIT => MailerContentEncoder::endWithLineBreak(
                text: MailerContentEncoder::normalizeBreaks(text: $string),
            ),
            MailerEncodingEnum::BINARY => $string,
            MailerEncodingEnum::QUOTED_PRINTABLE => MailerContentEncoder::normalizeBreaks(
                text: quoted_printable_encode(string: $string),
            ),
        };
    }

    /**
     * Converts every kind of line break (`\r\n`, `\r`, `\n`) to `\r\n`.
     */
    public static function normalizeBreaks(string $text): string
    {
        $text = str_replace(search: [MailerConstants::CRLF, "\r"], replace: "\n", subject: $text);

        return str_replace(search: "\n", replace: MailerConstants::CRLF, subject: $text);
    }

    public static function has8bitChars(string $text): bool
    {
        return MailerContentEncoder::matches(pattern: '/[\x80-\xFF]/', subject: $text);
    }

    /**
     * Lines of 1000 characters and more (998 without the line break) do not fit into a message with a 7bit or 8bit
     * encoding (RFC 5322 section 2.1.1).
     */
    public static function hasLineLongerThanMaximum(string $text): bool
    {
        return MailerContentEncoder::matches(
            pattern: '/^(.{' . (MailerConstants::MAX_LINE_LENGTH + strlen(string: MailerConstants::CRLF)) . ',})/m',
            subject: $text,
        );
    }

    private static function endWithLineBreak(string $text): string
    {
        if (str_ends_with(haystack: $text, needle: MailerConstants::CRLF)) {
            return $text;
        }

        return $text . MailerConstants::CRLF;
    }

    private static function matches(string $pattern, string $subject): bool
    {
        $result = preg_match(pattern: $pattern, subject: $subject);
        if ($result === false) {
            throw new MailerException(message: 'Could not check the text with the pattern ' . $pattern);
        }

        return $result === 1;
    }
}
