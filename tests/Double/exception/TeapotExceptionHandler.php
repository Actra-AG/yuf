<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\exception;

use actra\yuf\core\HttpResponse;
use actra\yuf\core\HttpStatusCodeEnum;
use actra\yuf\exception\ExceptionHandler;
use Override;
use Throwable;

/**
 * A project's own handler: answers every unexpected error with 502 and a text of its own, and sees the content type.
 */
final class TeapotExceptionHandler extends ExceptionHandler
{
    #[Override]
    protected function createDefaultResponse(Throwable $throwable): HttpResponse
    {
        return $this->createErrorResponse(
            httpStatusCode: HttpStatusCodeEnum::HTTP_BAD_GATEWAY,
            errorMessage: 'Try again later',
            errorCode: 502,
            htmlFileName: 'default.html',
        );
    }
}
