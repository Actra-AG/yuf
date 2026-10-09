<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   LGPL-2.1-only
 */

declare(strict_types=1);
/**
 * Derived work from the SMTP class of PHPMailer, reduced to the code needed by this Framework.
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
 * The connection to an SMTP server, so that `SmtpMailer` can be tested without a network.
 */
interface SmtpTransport
{
    /**
     * @throws MailerException if the connection cannot be opened
     */
    public function open(string $hostName, int $port, int $timeoutSeconds): void;

    public function isOpen(): bool;

    /**
     * @return string|null one line of the server with its line break, `null` if the server closed the connection
     *
     * @throws MailerException if the server does not answer within the time
     */
    public function readLine(int $timeoutSeconds): ?string;

    /**
     * Sends the line and the line break `\r\n`.
     *
     * @throws MailerException if the line cannot be sent
     */
    public function writeLine(string $line): void;

    /**
     * Starts TLS on the open connection (after `STARTTLS`). The certificate of the server must be valid for the host
     * name of `open()`.
     *
     * @return bool whether the connection is encrypted now
     */
    public function enableTls(): bool;

    public function close(): void;
}
