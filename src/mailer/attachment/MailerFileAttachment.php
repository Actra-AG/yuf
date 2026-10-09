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

namespace actra\yuf\mailer\attachment;

use actra\yuf\mailer\MailerEncodingEnum;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\MailerFileName;
use actra\yuf\mailer\MailerMimeTypes;
use Override;

/**
 * An attachment that is read from a file when the message is sent.
 *
 * The path is trusted: never pass a path that comes from a user without resolving it into an allowed directory. The
 * recipient only sees the file name, not the path.
 */
final readonly class MailerFileAttachment implements MailerAttachment
{
    public string $path;
    #[Override]
    public string $fileName;
    #[Override]
    public string $type;

    /**
     * @param string $fileName the name the recipient sees (only its last path segment is used), empty for the name of
     *                         the file
     * @param string $type `type/subtype`, empty for the type of the file extension
     */
    public function __construct(
        string $path,
        string $fileName = '',
        #[Override]
        public MailerEncodingEnum $encoding = MailerEncodingEnum::BASE64,
        string $type = '',
        #[Override]
        public bool $dispositionInline = false,
    ) {
        $path = trim(string: $path);
        if ($path === '') {
            throw new MailerException(message: 'Empty path.');
        }
        if (!MailerFileAttachment::isAccessible(path: $path)) {
            throw new MailerException(message: 'Could not access file: ' . $path);
        }
        $this->path = $path;
        $fileName = MailerFileName::sanitizeAttachmentName(fileName: $fileName);
        if ($fileName === '') {
            $fileName = MailerFileName::sanitizeAttachmentName(fileName: $path);
        }
        if ($fileName === '') {
            throw new MailerException(message: 'The attachment has no file name: ' . $path);
        }
        $this->fileName = $fileName;
        $type = trim(string: $type);
        if ($type === '') {
            $type = MailerMimeTypes::getByFileName(fileName: $path);
        }
        if (!MailerMimeTypes::isValidType(type: $type)) {
            throw new MailerException(message: 'Invalid type of the attachment: use type/subtype, e.g. text/plain.');
        }
        $this->type = $type;
    }

    #[Override]
    public function getContent(): string
    {
        if (!MailerFileAttachment::isAccessible(path: $this->path)) {
            throw new MailerException(message: 'File Error: Could not open file: ' . $this->path);
        }
        $content = file_get_contents(filename: $this->path);
        if ($content === false) {
            throw new MailerException(message: 'File Error: Could not open file: ' . $this->path);
        }

        return $content;
    }

    /**
     * A path with a stream wrapper (`http://`, `phar://`, `file://`, ...) is no file of this server and is never
     * read.
     */
    private static function isAccessible(string $path): bool
    {
        if (preg_match(pattern: '#^[a-z][a-z\d+.-]*://#i', subject: $path) === 1) {
            return false;
        }
        // A UNC path (starts with \\) is not checked for read permission
        if (str_starts_with(haystack: $path, needle: '\\\\')) {
            return file_exists(filename: $path);
        }

        return is_file(filename: $path) && is_readable(filename: $path);
    }
}
