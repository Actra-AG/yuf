<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Content transfer encodings (RFC 2045).
 */
enum MailerEncodingEnum: string
{
    case SEVEN_BIT = '7bit';
    case EIGHT_BIT = '8bit';
    case BASE64 = 'base64';
    case BINARY = 'binary';
    case QUOTED_PRINTABLE = 'quoted-printable';
}
