<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * What the log of a login attempt needs to know about the request.
 *
 * @internal
 */
final readonly class LoginAttempt
{
    public function __construct(public string $sessionId, public string $ipAddress, public string $userName) {}
}
