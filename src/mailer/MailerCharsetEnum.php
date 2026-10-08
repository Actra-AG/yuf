<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Character sets of a message (header and body parts).
 */
enum MailerCharsetEnum: string
{
    case ASCII = 'us-ascii';
    case UTF8 = 'utf-8';
}
