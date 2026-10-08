<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\mailer;

use Override;

/**
 * Hands the message to the `sendmail` of the server with `mail()`.
 */
final readonly class NativeMailFunction implements MailFunction
{
    #[Override]
    public function send(
        string $to,
        string $subject,
        string $message,
        string $additionalHeaders,
        string $additionalParameters,
    ): bool {
        return mail(
            to: $to,
            subject: $subject,
            message: $message,
            additional_headers: $additionalHeaders,
            additional_params: $additionalParameters,
        );
    }
}
