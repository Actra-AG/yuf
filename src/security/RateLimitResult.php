<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

/**
 * The answer of `RateLimiter::consume()`.
 */
final readonly class RateLimitResult
{
    /**
     * @param int $retryAfterSeconds Until the last exceeded window ends (for a `Retry-After` header), 0 if allowed
     */
    private function __construct(
        public bool $isAllowed,
        public int $retryAfterSeconds,
    ) {}

    public static function allowed(): RateLimitResult
    {
        return new RateLimitResult(isAllowed: true, retryAfterSeconds: 0);
    }

    public static function blocked(int $retryAfterSeconds): RateLimitResult
    {
        return new RateLimitResult(isAllowed: false, retryAfterSeconds: $retryAfterSeconds);
    }
}
