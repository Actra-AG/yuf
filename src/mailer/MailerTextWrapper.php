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
 * Wraps text at word boundaries, also quoted-printable encoded text and encoded header words.
 *
 * Static on purpose: pure function without state.
 *
 * @internal
 */
final readonly class MailerTextWrapper
{
    /**
     * @param bool $qpMode the message is quoted-printable encoded: a word longer than the line is split (never inside
     *                     an encoded character) and the soft line breaks are marked with `=`
     */
    public static function wrap(string $message, int $length, MailerCharsetEnum $charSet, bool $qpMode): string
    {
        $softBreak = $qpMode ? ' =' . MailerConstants::CRLF : MailerConstants::CRLF;
        $lineBreakLength = strlen(string: MailerConstants::CRLF);

        $message = MailerContentEncoder::normalizeBreaks(text: $message);
        // Remove a trailing line break
        if (str_ends_with(haystack: $message, needle: MailerConstants::CRLF)) {
            $message = substr(string: $message, offset: 0, length: -$lineBreakLength);
        }

        $wrapped = '';
        foreach (explode(separator: MailerConstants::CRLF, string: $message) as $line) {
            $wrapped .= MailerTextWrapper::wrapLine(
                line: $line,
                length: $length,
                charSet: $charSet,
                qpMode: $qpMode,
                softBreak: $softBreak,
            ) . MailerConstants::CRLF;
        }

        return $wrapped;
    }

    /**
     * @return string the line with its inner breaks and without the final line break
     */
    private static function wrapLine(
        string $line,
        int $length,
        MailerCharsetEnum $charSet,
        bool $qpMode,
        string $softBreak,
    ): string {
        $lineBreakLength = strlen(string: MailerConstants::CRLF);
        $message = '';
        $buffer = '';
        $firstWord = true;
        foreach (explode(separator: ' ', string: $line) as $word) {
            if ($qpMode && strlen(string: $word) > $length) {
                $spaceLeft = $length - strlen(string: $buffer) - $lineBreakLength;
                if (!$firstWord) {
                    if ($spaceLeft > 20) {
                        $partLength = MailerTextWrapper::splitLength(
                            length: $spaceLeft,
                            charSet: $charSet,
                            word: $word,
                        );
                        $buffer .= ' ' . substr(string: $word, offset: 0, length: $partLength);
                        $word = substr(string: $word, offset: $partLength);
                        $message .= $buffer . '=' . MailerConstants::CRLF;
                    } else {
                        $message .= $buffer . $softBreak;
                    }
                    $buffer = '';
                }
                while ($word !== '' && $length > 0) {
                    $partLength = MailerTextWrapper::splitLength(length: $length, charSet: $charSet, word: $word);
                    $part = substr(string: $word, offset: 0, length: $partLength);
                    $word = substr(string: $word, offset: $partLength);
                    if ($word !== '') {
                        $message .= $part . '=' . MailerConstants::CRLF;
                    } else {
                        $buffer = $part;
                    }
                }
            } else {
                $previousBuffer = $buffer;
                if (!$firstWord) {
                    $buffer .= ' ';
                }
                $buffer .= $word;
                if ($previousBuffer !== '' && strlen(string: $buffer) > $length) {
                    $message .= $previousBuffer . $softBreak;
                    $buffer = $word;
                }
            }
            $firstWord = false;
        }

        return $message . $buffer;
    }

    /**
     * The number of characters of the word that fit in the length without splitting an encoded character (`=XX`),
     * for UTF-8 also not a multi-byte character.
     */
    private static function splitLength(int $length, MailerCharsetEnum $charSet, string $word): int
    {
        if ($charSet === MailerCharsetEnum::UTF8) {
            return MailerTextWrapper::utf8CharBoundary(encodedText: $word, maxLength: $length);
        }
        if (substr(string: $word, offset: $length - 1, length: 1) === '=') {
            return $length - 1;
        }
        if (substr(string: $word, offset: $length - 2, length: 1) === '=') {
            return $length - 2;
        }

        return $length;
    }

    private static function utf8CharBoundary(string $encodedText, int $maxLength): int
    {
        $lookBack = 3;
        while (true) {
            if ($lookBack > $maxLength) {
                return $maxLength;
            }
            $lastChunk = substr(string: $encodedText, offset: $maxLength - $lookBack, length: $lookBack);
            $encodedCharPos = strpos(haystack: $lastChunk, needle: '=');
            if ($encodedCharPos === false) {
                // No encoded character found
                return $maxLength;
            }
            // Found the start of an encoded byte within the look back block: check its value (the 2 characters after
            // the '=')
            $hex = substr(string: $encodedText, offset: $maxLength - $lookBack + $encodedCharPos + 1, length: 2);
            $decimal = hexdec(hex_string: $hex);
            if ($decimal < 128) {
                // Single byte character: it fits if it starts at position 0, otherwise split before it
                return $encodedCharPos > 0 ? $maxLength - ($lookBack - $encodedCharPos) : $maxLength;
            }
            if ($decimal >= 192) {
                // First byte of a multibyte character: split before it
                return $maxLength - ($lookBack - $encodedCharPos);
            }
            // Middle byte of a multibyte character: look further back
            $lookBack += 3;
        }
    }
}
