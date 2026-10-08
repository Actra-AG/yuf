<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

/**
 * The PHP function `mail()`, so that `MailMailer` can be tested without sending a message.
 */
interface MailFunction
{
    /**
     * @param string $additionalHeaders all header lines of the message except `To` and `Subject`
     * @param string $additionalParameters command line arguments of `sendmail`: must be shell safe
     *
     * @return bool whether the message was accepted for delivery
     */
    public function send(
        string $to,
        string $subject,
        string $message,
        string $additionalHeaders,
        string $additionalParameters,
    ): bool;
}
