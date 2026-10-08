<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\api;

/**
 * @internal
 */
final readonly class CurlResponseError
{
    public function __construct(
        public int $code,
        public string $message,
    ) {}
}
