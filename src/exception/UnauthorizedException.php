<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\HttpStatusCodeEnum;
use Exception;

/**
 * The user is not allowed to do this: answered with the status 401 and the error page `unauthorized.html`.
 * Production shows a fixed text, never the message (it is for the log and the debug page).
 *
 * Extension point: the reasons have their own types (`UnauthorizedAccessRightException`,
 * `UnauthorizedIpAddressException`), which projects can catch separately.
 */
class UnauthorizedException extends Exception
{
    public function __construct(
        string $message = 'Unauthorized',
        HttpStatusCodeEnum $code = HttpStatusCodeEnum::HTTP_UNAUTHORIZED,
    ) {
        parent::__construct(
            message: $message,
            code: $code->value,
        );
    }
}
