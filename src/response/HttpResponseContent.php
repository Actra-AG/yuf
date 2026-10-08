<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\response;

/**
 * The body of a JSON or text response of a view; created by `HttpSuccessResponseContent` and
 * `HttpErrorResponseContent`.
 */
final readonly class HttpResponseContent
{
    public function __construct(public string $content) {}
}
