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
 * Encodes the text of headers (subject, names, custom headers) as RFC 2047 encoded words and removes line breaks.
 *
 * Static on purpose: pure functions without state.
 *
 * @internal
 */
final readonly class MailerHeaderEncoder
{
    /**
     * Removes all line breaks and trims. Use it for every value that comes from outside before it is put in a header:
     * a line break would start a new header line.
     */
    public static function secure(string $string): string
    {
        return trim(string: str_replace(search: ["\r", "\n"], replace: '', subject: $string));
    }

    /**
     * Encodes the text of an unstructured header (subject, custom headers). Several encoded words are separated by
     * `\r\n `.
     */
    public static function encodeText(string $string, int $maxLineLength, MailerCharsetEnum $defaultCharSet): string
    {
        return MailerHeaderEncoder::encode(
            string: $string,
            maxLineLength: $maxLineLength,
            matchCount: MailerHeaderEncoder::countMatches(
                pattern: '/[\000-\010\013\014\016-\037\177-\377]/',
                subject: $string,
            ),
            defaultCharSet: $defaultCharSet,
            isPhrase: false,
        );
    }

    /**
     * Encodes a name for the display name of an address: quoted if it contains special characters, as encoded words
     * if it contains non-ASCII characters.
     */
    public static function encodePhrase(string $string, int $maxLineLength, MailerCharsetEnum $defaultCharSet): string
    {
        if (!MailerContentEncoder::has8bitChars(text: $string)) {
            $escaped = addcslashes(string: $string, characters: "\0..\37\177\\\"");
            if (
                $string === $escaped
                && preg_match(pattern: '/[^A-Za-z\d!#$%&\'*+\/=?^_`{|}~ -]/', subject: $string) !== 1
            ) {
                return $escaped;
            }

            return '"' . $escaped . '"';
        }

        return MailerHeaderEncoder::encode(
            string: $string,
            maxLineLength: $maxLineLength,
            matchCount: MailerHeaderEncoder::countMatches(pattern: '/[^\040\041\043-\133\135-\176]/', subject: $string),
            defaultCharSet: $defaultCharSet,
            isPhrase: true,
        );
    }

    private static function encode(
        string $string,
        int $maxLineLength,
        int $matchCount,
        MailerCharsetEnum $defaultCharSet,
        bool $isPhrase,
    ): string {
        $charSet = MailerContentEncoder::has8bitChars(text: $string) ? $defaultCharSet : MailerCharsetEnum::ASCII;
        // Q/B encoding adds 8 characters and the charset ("` =?<charset>?[QB]?<content>?=`")
        $maxLength = $maxLineLength - (8 + strlen(string: $charSet->value));

        // Select the encoding that produces the shortest output and/or prevents corruption
        $encoding = match (true) {
            // More than 1/3 of the content needs encoding: B-encode
            $matchCount > strlen(string: $string) / 3 => 'B',
            // Less than 1/3 of the content needs encoding: Q-encode
            $matchCount > 0 => 'Q',
            // No encoding needed, but the value exceeds the maximum line length: Q-encode to prevent corruption
            strlen(string: $string) > $maxLength => 'Q',
            default => null,
        };
        if ($encoding === null) {
            return $string;
        }

        $encoded = $encoding === 'B'
            ? MailerHeaderEncoder::base64Words(string: $string, charSet: $charSet, maxLength: $maxLength)
            : MailerHeaderEncoder::quotedPrintableWords(
                string: $string,
                maxLength: $maxLength,
                defaultCharSet: $defaultCharSet,
                isPhrase: $isPhrase,
            );
        $words = preg_replace(
            pattern: '/^(.*)$/m',
            replacement: ' =?' . $charSet->value . '?' . $encoding . '?\\1?=',
            subject: $encoded,
        );
        if ($words === null) {
            throw new MailerException(message: 'Could not encode the header text.');
        }

        return trim(string: MailerContentEncoder::normalizeBreaks(text: $words));
    }

    private static function base64Words(string $string, MailerCharsetEnum $charSet, int $maxLength): string
    {
        if (strlen(string: $string) > mb_strlen(string: $string, encoding: $charSet->value)) {
            // Encodes and wraps long multibyte strings without breaking lines within a character
            return MailerHeaderEncoder::base64EncodeWrapMb(string: $string, charSet: $charSet);
        }
        $maxLength = max(4, $maxLength - $maxLength % 4);

        return trim(string: chunk_split(string: base64_encode(string: $string), length: $maxLength));
    }

    private static function quotedPrintableWords(
        string $string,
        int $maxLength,
        MailerCharsetEnum $defaultCharSet,
        bool $isPhrase,
    ): string {
        $wrapped = MailerTextWrapper::wrap(
            message: MailerHeaderEncoder::encodeQ(string: $string, isPhrase: $isPhrase),
            length: $maxLength,
            charSet: $defaultCharSet,
            qpMode: true,
        );

        return str_replace(search: '=' . MailerConstants::CRLF, replace: "\n", subject: trim(string: $wrapped));
    }

    private static function base64EncodeWrapMb(string $string, MailerCharsetEnum $charSet): string
    {
        $lineBreak = "\n";
        $start = '=?' . $charSet->value . '?B?';
        $end = '?=';
        $encoded = '';

        $multiByteLength = mb_strlen(string: $string, encoding: $charSet->value);
        // Each line must have length <= 75, including $start and $end
        $length = 75 - strlen(string: $start) - strlen(string: $end);
        // Average multi-byte ratio
        $ratio = $multiByteLength / strlen(string: $string);
        // Base64 has a 4:3 ratio
        $averageLength = (int) floor(num: $length * $ratio * .75);

        $position = 0;
        while ($position < $multiByteLength) {
            $lookBack = 0;
            do {
                $chunkLength = $averageLength - $lookBack;
                $chunk = base64_encode(
                    string: mb_substr(
                        string: $string,
                        start: $position,
                        length: $chunkLength,
                        encoding: $charSet->value,
                    ),
                );
                ++$lookBack;
            } while (strlen(string: $chunk) > $length);
            $encoded .= $chunk . $lineBreak;
            $position += $chunkLength;
        }

        // Chomp the last line feed
        return substr(string: $encoded, offset: 0, length: -strlen(string: $lineBreak));
    }

    private static function encodeQ(string $string, bool $isPhrase): string
    {
        // There should not be any line break in the string
        $encoded = str_replace(search: ["\r", "\n"], replace: '', subject: $string);
        $pattern = $isPhrase ? '^A-Za-z0-9!*+\/ -' : '\000-\011\013\014\016-\037\075\077\137\177-\377';
        $matches = [];
        if (preg_match_all(pattern: "/[$pattern]/", subject: $encoded, matches: $matches) > 0) {
            $characters = $matches[0];
            // If the string contains an '=', make sure it's the first thing replaced so as to avoid double-encoding
            $equalSignKey = array_search(needle: '=', haystack: $characters, strict: true);
            if ($equalSignKey !== false) {
                unset($characters[$equalSignKey]);
                array_unshift($characters, '=');
            }
            foreach (array_unique(array: $characters) as $character) {
                $encoded = str_replace(
                    search: $character,
                    replace: '=' . sprintf('%02X', ord(character: $character)),
                    subject: $encoded,
                );
            }
        }

        // Replace spaces with _ (more readable than =20), RFC 2047 section 4.2(2)
        return str_replace(search: ' ', replace: '_', subject: $encoded);
    }

    private static function countMatches(string $pattern, string $subject): int
    {
        $count = preg_match_all(pattern: $pattern, subject: $subject);
        if ($count === false) {
            throw new MailerException(message: 'Could not check the header text with the pattern ' . $pattern);
        }

        return $count;
    }
}
