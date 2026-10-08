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

namespace actra\yuf\mailer\attachment;

use actra\yuf\mailer\MailerEncodingEnum;
use actra\yuf\mailer\MailerException;
use actra\yuf\mailer\MailerFileName;
use actra\yuf\mailer\MailerMimeTypes;
use Override;

/**
 * An attachment whose content is a string in memory. It is sent base64 encoded.
 */
final readonly class MailerStringAttachment implements MailerAttachment
{
    public string $contentString;
    #[Override]
    public string $fileName;
    #[Override]
    public string $type;
    #[Override]
    public MailerEncodingEnum $encoding;

    /**
     * @param string $fileName the name the recipient sees; only its last path segment is used
     * @param string $type `type/subtype`, empty for the type of the file extension
     */
    public function __construct(
        string $contentString,
        string $fileName,
        string $type,
        #[Override]
        public bool $dispositionInline = false,
    ) {
        $fileName = MailerFileName::sanitizeAttachmentName(fileName: $fileName);
        if ($contentString === '' || $fileName === '') {
            throw new MailerException(message: 'Empty contentString or fileName.');
        }
        $type = trim(string: $type);
        if ($type === '') {
            $type = MailerMimeTypes::getByFileName(fileName: $fileName);
        }
        if (!MailerMimeTypes::isValidType(type: $type)) {
            throw new MailerException(message: 'Invalid type of the attachment: use type/subtype, e.g. text/plain.');
        }
        $this->encoding = MailerEncodingEnum::BASE64;
        $this->contentString = $contentString;
        $this->fileName = $fileName;
        $this->type = $type;
    }

    #[Override]
    public function getContent(): string
    {
        return $this->contentString;
    }
}
