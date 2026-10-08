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
 * Splits file names and paths of both directory separator styles, `/` and `\`, whatever the server runs on (unlike
 * `pathinfo()`, which only knows the one of the platform and breaks multibyte characters in some locales).
 *
 * Static on purpose: pure functions without state.
 *
 * @internal
 */
final readonly class MailerFileName
{
    private const string PATH_PATTERN = '#^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^.\\\\/]+?)|))[\\\\/.]*$#m';

    /**
     * The last part of a path, `report.pdf` for `a/b/report.pdf` and `a\b\report.pdf`.
     */
    public static function baseName(string $path): string
    {
        return MailerFileName::parse(path: $path)['baseName'];
    }

    /**
     * The extension without the dot, empty if the path has none.
     */
    public static function extension(string $path): string
    {
        return MailerFileName::parse(path: $path)['extension'];
    }

    /**
     * The name of an attachment as it goes into `Content-Type` and `Content-Disposition`: no directories (a name from
     * outside must not suggest a path to the recipient), no control characters, trimmed.
     */
    public static function sanitizeAttachmentName(string $fileName): string
    {
        $withoutControlCharacters = preg_replace(pattern: '/[\x00-\x1F\x7F]/', replacement: '', subject: $fileName);
        if ($withoutControlCharacters === null) {
            throw new MailerException(message: 'Could not check the attachment name.');
        }

        return trim(string: MailerFileName::baseName(path: trim(string: $withoutControlCharacters)));
    }

    /**
     * @return array{baseName: string, extension: string}
     */
    private static function parse(string $path): array
    {
        $matches = [];
        if (preg_match(pattern: MailerFileName::PATH_PATTERN, subject: $path, matches: $matches) !== 1) {
            return ['baseName' => '', 'extension' => ''];
        }

        return [
            'baseName' => $matches[2],
            'extension' => array_key_exists(key: 5, array: $matches) ? $matches[5] : '',
        ];
    }
}
