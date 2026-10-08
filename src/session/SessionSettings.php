<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\session;

use InvalidArgumentException;

/**
 * The settings of the PHP session that the session handler applies before it starts the session.
 */
final readonly class SessionSettings
{
    /**
     * @param ?string $savePath Where file sessions are stored, `null` for the default of the project
     * @param string $individualName The name of the session cookie, empty for PHP's default
     * @param int $maxLifeTime Seconds without activity until a session expires
     * @param int $gcProbability With `$gcDivisor`: the chance that a request collects expired sessions
     * @param bool $isSameSiteStrict `SameSite=Strict` for the session cookie, `Lax` otherwise
     *
     * @throws InvalidArgumentException if a number is out of range
     */
    public function __construct(
        public ?string $savePath = null,
        public string $individualName = '',
        public int $maxLifeTime = 3600,
        public int $gcProbability = 1,
        public int $gcDivisor = 100000,
        public bool $isSameSiteStrict = true,
    ) {
        if ($maxLifeTime < 1) {
            throw new InvalidArgumentException(message: 'The session lifetime must be at least one second.');
        }
        if ($gcProbability < 0 || $gcDivisor < 1) {
            throw new InvalidArgumentException(
                message: 'The garbage collection probability must not be negative and its divisor must be at least 1.',
            );
        }
    }
}
