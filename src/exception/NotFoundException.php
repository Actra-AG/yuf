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
 * The requested page or object does not exist: answered with the status 404 and the error page `notFound.html`.
 * Production shows a fixed text, never the message (it is for the log and the debug page).
 */
final class NotFoundException extends Exception
{
    public function __construct(
        string $message = '',
        HttpStatusCodeEnum $code = HttpStatusCodeEnum::HTTP_NOT_FOUND,
    ) {
        if ($message === '') {
            $message = 'Not Found';
        }
        parent::__construct(
            message: $message,
            code: $code->value,
        );
    }
}
