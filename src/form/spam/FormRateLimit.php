<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\form\spam;

use actra\yuf\core\Logger;
use actra\yuf\security\RateLimit;
use actra\yuf\security\RateLimiter;
use actra\yuf\security\RateLimitFailModeEnum;
use actra\yuf\security\RateLimitGate;
use actra\yuf\security\RateLimitResult;
use InvalidArgumentException;

/**
 * The rate limit of a public form (`SpamProtection::$rateLimit`): every submit with a valid CSRF token is counted per
 * client IP address (`RateLimiter::ipBucket()`) and form, valid or not, and then for all clients of the form if
 * `$globalLimits` are given. Keys: `form|<form name>|<ip bucket>` and `form|<form name>`.
 */
final readonly class FormRateLimit
{
    /**
     * @param RateLimitFailModeEnum $failMode Block or allow the submit when it cannot be counted (storage error,
     *     invalid client IP address)
     * @param Logger $logger Logs why a submit could not be counted
     * @param list<RateLimit> $clientLimits Per client IP address and form, at least one; by default 10 per 10 minutes
     *     and 50 per day
     * @param list<RateLimit> $globalLimits Per form for all clients, checked after the client limits; `[]` for none
     *
     * @throws InvalidArgumentException if `$clientLimits` is empty
     */
    public function __construct(
        public RateLimiter $rateLimiter,
        public RateLimitFailModeEnum $failMode,
        public Logger $logger,
        public array $clientLimits = [
            new RateLimit(maxHits: 10, windowSeconds: 600),
            new RateLimit(maxHits: 50, windowSeconds: 86400),
        ],
        public array $globalLimits = [],
    ) {
        if ($clientLimits === []) {
            throw new InvalidArgumentException(message: 'A form rate limit needs at least one client limit.');
        }
    }

    /**
     * Counts one submit of the form by the client.
     */
    public function consume(string $formName, string $ipAddress): RateLimitResult
    {
        return RateLimitGate::consume(
            rateLimiter: $this->rateLimiter,
            failMode: $this->failMode,
            logger: $this->logger,
            ipAddress: $ipAddress,
            clientKeyPrefix: 'form|' . $formName . '|',
            clientLimits: $this->clientLimits,
            limitsByKey: ['form|' . $formName => $this->globalLimits],
        );
    }
}
