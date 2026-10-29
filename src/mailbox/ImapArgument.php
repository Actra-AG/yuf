<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailbox;

use InvalidArgumentException;

/**
 * Formats the arguments of IMAP commands (RFC 9051 section 4.3): a quoted string, or a literal for 8-bit values.
 * Line breaks and NUL are rejected, so an argument can never start a new command.
 *
 * @internal
 */
final class ImapArgument
{
    public static function string(string $value): string|ImapLiteral
    {
        if (preg_match(pattern: '/[\x00\r\n]/', subject: $value) === 1) {
            throw new InvalidArgumentException(message: 'An IMAP argument must not contain NUL or line breaks.');
        }
        if (preg_match(pattern: '/[\x80-\xFF]/', subject: $value) === 1) {
            return new ImapLiteral(bytes: $value);
        }

        return '"' . str_replace(search: ['\\', '"'], replace: ['\\\\', '\\"'], subject: $value) . '"';
    }

    /**
     * @param string $name UTF-8, encoded as modified UTF-7 (RFC 3501 section 5.1.3)
     */
    public static function folderName(string $name): string
    {
        if ($name === '' || !mb_check_encoding(value: $name, encoding: 'UTF-8')) {
            throw new InvalidArgumentException(message: 'An IMAP folder name must be non-empty UTF-8.');
        }
        $argument = ImapArgument::string(
            value: mb_convert_encoding(string: $name, to_encoding: 'UTF7-IMAP', from_encoding: 'UTF-8'),
        );

        if ($argument instanceof ImapLiteral) {
            throw new InvalidArgumentException(message: 'The folder name "' . $name . '" cannot be encoded.');
        }

        return $argument;
    }
}
