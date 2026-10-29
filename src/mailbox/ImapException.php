<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use RuntimeException;

/**
 * The IMAP server cannot be reached, rejected a command or answered something unexpected. The message and
 * `$serverResponse` contain the answer of the server, never the password.
 */
final class ImapException extends RuntimeException
{
    /**
     * @param string $serverResponse the line of the server that caused the error, `''` if there is none
     */
    public function __construct(string $message, public readonly string $serverResponse = '')
    {
        parent::__construct(message: $serverResponse === '' ? $message : $message . ' Server: ' . $serverResponse);
    }
}
