<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use actra\yuf\core\Logger;
use InvalidArgumentException;
use RuntimeException;

/**
 * Consumes the limits of a client IP address and then further keys (`RateLimiter::consume()` per key, the client first,
 * so one client uses up only its own budget) and applies the fail mode when it cannot count. Shared by
 * `FormRateLimit` and `LoginRateLimit`.
 *
 * @internal
 */
final readonly class RateLimitGate
{
    /** The retry time of a request blocked because the limit could not be counted */
    private const int FAIL_CLOSED_RETRY_SECONDS = 60;

    /**
     * @param string $clientKeyPrefix Prepended to `RateLimiter::ipBucket()` of the IP address
     * @param list<RateLimit> $clientLimits
     * @param array<string, list<RateLimit>> $limitsByKey Consumed in this order after the client limits; empty lists
     *     are skipped
     */
    public static function consume(
        RateLimiter $rateLimiter,
        RateLimitFailModeEnum $failMode,
        Logger $logger,
        string $ipAddress,
        string $clientKeyPrefix,
        array $clientLimits,
        array $limitsByKey,
    ): RateLimitResult {
        try {
            $result = $rateLimiter->consume(
                $clientKeyPrefix . RateLimiter::ipBucket(ipAddress: $ipAddress),
                ...$clientLimits,
            );
            foreach ($limitsByKey as $key => $limits) {
                if (!$result->isAllowed) {
                    break;
                }
                if ($limits !== []) {
                    $result = $rateLimiter->consume($key, ...$limits);
                }
            }

            return $result;
        } catch (InvalidArgumentException|RuntimeException $exception) {
            $logger->logException(throwable: $exception);

            return $failMode === RateLimitFailModeEnum::OPEN
                ? RateLimitResult::allowed()
                : RateLimitResult::blocked(retryAfterSeconds: RateLimitGate::FAIL_CLOSED_RETRY_SECONDS);
        }
    }
}
