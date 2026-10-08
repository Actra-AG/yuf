<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * Value of the header `X-Priority`.
 */
enum MailerPriorityEnum: int
{
    case HIGH = 1;
    case NORMAL = 3;
    case LOW = 5;
}
