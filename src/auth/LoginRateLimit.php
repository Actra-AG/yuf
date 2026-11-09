<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\core\Logger;
use actra\yuf\security\RateLimit;
use actra\yuf\security\RateLimiter;
use actra\yuf\security\RateLimitFailModeEnum;
use actra\yuf\security\RateLimitGate;
use actra\yuf\security\RateLimitResult;
use InvalidArgumentException;

/**
 * The rate limit of the logins of an `Authenticator`: every attempt (login, password verification, token request) is
 * counted per client IP address (`RateLimiter::ipBucket()`, key `login|ip|<ip bucket>`) and then per account name
 * (lower case, trimmed, key `login|account|<name>`), before the user is loaded. An unknown account name is counted
 * like an existing one, so the answer does not tell which accounts exist.
 *
 * The one-time codes of a login with a second step (`Authenticator::consumeCodeAttempt()`) are counted per client IP
 * address under their own key (`login|code|ip|<ip bucket>`), so code attempts and logins do not use up each other's
 * budget.
 *
 * Anyone can use up the account limit of a name for the current window by trying it from many IP addresses; keep
 * the account window short (the default: 10 per 15 minutes). The lock-out of the user in the database
 * (`AuthUser::registerWrongPasswordAttempt()`) stays the protection against slow guessing.
 */
final readonly class LoginRateLimit
{
    /**
     * @param RateLimitFailModeEnum $failMode Block or allow the attempt when it cannot be counted (storage error,
     *     invalid client IP address); `CLOSED` is recommended for logins
     * @param Logger $logger Logs why an attempt could not be counted
     * @param list<RateLimit> $clientLimits Per client IP address, at least one; by default 20 per 15 minutes and 100
     *     per day
     * @param list<RateLimit> $accountLimits Per account name, at least one; by default 10 per 15 minutes
     * @param list<RateLimit> $codeLimits Code attempts per client IP address, at least one; by default 30 per hour
     *
     * @throws InvalidArgumentException if a list of limits is empty
     */
    public function __construct(
        public RateLimiter $rateLimiter,
        public RateLimitFailModeEnum $failMode,
        public Logger $logger,
        public array $clientLimits = [
            new RateLimit(maxHits: 20, windowSeconds: 900),
            new RateLimit(maxHits: 100, windowSeconds: 86400),
        ],
        public array $accountLimits = [new RateLimit(maxHits: 10, windowSeconds: 900)],
        public array $codeLimits = [new RateLimit(maxHits: 30, windowSeconds: 3600)],
    ) {
        if ($clientLimits === [] || $accountLimits === [] || $codeLimits === []) {
            throw new InvalidArgumentException(
                message: 'A login rate limit needs at least one client limit, one account limit and one code limit.',
            );
        }
    }

    /**
     * Counts one attempt of the client for the account name.
     */
    public function consume(string $userName, string $ipAddress): RateLimitResult
    {
        return RateLimitGate::consume(
            rateLimiter: $this->rateLimiter,
            failMode: $this->failMode,
            logger: $this->logger,
            ipAddress: $ipAddress,
            clientKeyPrefix: 'login|ip|',
            clientLimits: $this->clientLimits,
            limitsByKey: ['login|account|' . mb_strtolower(string: trim(string: $userName)) => $this->accountLimits],
        );
    }

    /**
     * Counts one attempt of the client to enter a one-time code (second step of a login), right or wrong.
     */
    public function consumeCode(string $ipAddress): RateLimitResult
    {
        return RateLimitGate::consume(
            rateLimiter: $this->rateLimiter,
            failMode: $this->failMode,
            logger: $this->logger,
            ipAddress: $ipAddress,
            clientKeyPrefix: 'login|code|ip|',
            clientLimits: $this->codeLimits,
            limitsByKey: [],
        );
    }
}
