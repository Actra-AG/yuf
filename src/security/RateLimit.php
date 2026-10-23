<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use InvalidArgumentException;

/**
 * At most `$maxHits` hits per fixed window of `$windowSeconds` (aligned to the Unix epoch, so a window of 86400 seconds
 * is one UTC day), counted by `RateLimiter::consume()`.
 */
final readonly class RateLimit
{
    /**
     * @throws InvalidArgumentException if a value is less than 1
     */
    public function __construct(
        public int $maxHits,
        public int $windowSeconds,
    ) {
        if ($maxHits < 1 || $windowSeconds < 1) {
            throw new InvalidArgumentException(message: 'The maximum hits and the window must be at least 1.');
        }
    }
}
