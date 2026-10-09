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
 * One header line. A line break in the name or the value would start a new header (header injection): the only line
 * breaks allowed are the folds `\r\n ` of encoded words.
 *
 * @internal
 */
final readonly class MailerHeader
{
    private string $name;
    private string $value;

    private function __construct(
        string $name,
        string $value,
    ) {
        $name = trim(string: $name);
        $value = trim(string: $value);

        // RFC 5322 section 2.2: the name consists of printable US-ASCII characters except the colon
        if (preg_match(pattern: '/^[\x21-\x39\x3B-\x7E]+$/D', subject: $name) !== 1) {
            throw new MailerException(message: 'Invalid header name: use printable ASCII characters without colon.');
        }
        $withoutFolds = preg_replace(pattern: '/\r\n(?=[ \t])/', replacement: '', subject: $value);
        if ($withoutFolds === null || strpbrk(string: $withoutFolds, characters: MailerConstants::CRLF) !== false) {
            throw new MailerException(message: 'Invalid header value: it contains a line break.');
        }
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * @param string $value already encoded: only a line break followed by white space is accepted
     */
    public static function createRaw(
        string $name,
        string $value,
    ): string {
        return new MailerHeader(
            name: $name,
            value: $value,
        )->get();
    }

    /**
     * @param string $value text of the header: it may contain any character but line breaks
     */
    public static function createEncodedHeaderText(
        string $name,
        string $value,
        int $maxLineLength,
        MailerCharsetEnum $defaultCharSet,
    ): MailerHeader {
        // Line breaks are no characters to encode: reject them before the encoder can pass them on
        if (strpbrk(string: $value, characters: MailerConstants::CRLF) !== false) {
            throw new MailerException(message: 'Invalid header value: it contains a line break.');
        }

        return new MailerHeader(
            name: $name,
            value: MailerHeaderEncoder::encodeText(
                string: $value,
                maxLineLength: $maxLineLength,
                defaultCharSet: $defaultCharSet,
            ),
        );
    }

    public function get(): string
    {
        return $this->name . ': ' . $this->value . MailerConstants::CRLF;
    }
}
