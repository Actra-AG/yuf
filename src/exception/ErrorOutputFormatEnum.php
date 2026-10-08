<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\exception;

use actra\yuf\core\ContentType;

/**
 * How an error is answered: as JSON or plain text for machine-readable requests, as an error page for everything
 * else.
 *
 * @internal
 */
enum ErrorOutputFormatEnum
{
    case JSON;
    case TEXT;
    case HTML;

    public static function fromContentType(ContentType $contentType): ErrorOutputFormatEnum
    {
        return match (true) {
            $contentType->isJson() => ErrorOutputFormatEnum::JSON,
            $contentType->isTxt(), $contentType->isCsv() => ErrorOutputFormatEnum::TEXT,
            default => ErrorOutputFormatEnum::HTML,
        };
    }
}
