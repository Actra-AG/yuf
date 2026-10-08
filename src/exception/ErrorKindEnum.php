<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\HttpStatusCodeEnum;
use Throwable;

/**
 * The kinds of errors the exception handler answers differently: the error page, the status code and the text that
 * users see. Everything that is not a `NotFoundException` or an `UnauthorizedException` is an internal error.
 *
 * @internal
 */
enum ErrorKindEnum
{
    case NOT_FOUND;
    case UNAUTHORIZED;
    case INTERNAL_ERROR;

    public static function fromThrowable(Throwable $throwable): ErrorKindEnum
    {
        return match (true) {
            $throwable instanceof NotFoundException => ErrorKindEnum::NOT_FOUND,
            $throwable instanceof UnauthorizedException => ErrorKindEnum::UNAUTHORIZED,
            default => ErrorKindEnum::INTERNAL_ERROR,
        };
    }

    public function getHttpStatusCode(): HttpStatusCodeEnum
    {
        return match ($this) {
            ErrorKindEnum::NOT_FOUND => HttpStatusCodeEnum::HTTP_NOT_FOUND,
            ErrorKindEnum::UNAUTHORIZED => HttpStatusCodeEnum::HTTP_UNAUTHORIZED,
            ErrorKindEnum::INTERNAL_ERROR => HttpStatusCodeEnum::HTTP_INTERNAL_SERVER_ERROR,
        };
    }

    /**
     * The title of the debug page.
     */
    public function getTitle(): string
    {
        return match ($this) {
            ErrorKindEnum::NOT_FOUND => 'Page not found',
            ErrorKindEnum::UNAUTHORIZED => 'Unauthorized',
            ErrorKindEnum::INTERNAL_ERROR => 'Internal Server Error',
        };
    }

    /**
     * The error page file in the error docs directory.
     */
    public function getHtmlFileName(): string
    {
        return match ($this) {
            ErrorKindEnum::NOT_FOUND => 'notFound.html',
            ErrorKindEnum::UNAUTHORIZED => 'unauthorized.html',
            ErrorKindEnum::INTERNAL_ERROR => 'default.html',
        };
    }

    /**
     * The message of the error in production: a fixed text, never the message of the exception (it may name files,
     * queries or tokens).
     */
    public function getPublicMessage(): string
    {
        return match ($this) {
            ErrorKindEnum::NOT_FOUND => 'Not Found',
            ErrorKindEnum::UNAUTHORIZED => 'Unauthorized',
            ErrorKindEnum::INTERNAL_ERROR => 'Internal Server Error',
        };
    }
}
