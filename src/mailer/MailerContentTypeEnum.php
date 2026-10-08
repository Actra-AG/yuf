<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * The content types the mailer creates itself. The type of an attachment is any MIME type.
 */
enum MailerContentTypeEnum: string
{
    case TEXT_PLAIN = 'text/plain';
    case TEXT_HTML = 'text/html';
    case MULTIPART_ALTERNATIVE = 'multipart/alternative';
    case MULTIPART_MIXED = 'multipart/mixed';
    case MULTIPART_RELATED = 'multipart/related';
}
