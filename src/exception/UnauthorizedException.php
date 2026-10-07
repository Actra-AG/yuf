<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\HttpStatusCodeEnum;
use Exception;

class UnauthorizedException extends Exception
{
    public function __construct(
        $message = 'Unauthorized',
        HttpStatusCodeEnum $code = HttpStatusCodeEnum::HTTP_UNAUTHORIZED,
    ) {
        parent::__construct(
            message: $message,
            code: $code->value,
        );
    }
}
