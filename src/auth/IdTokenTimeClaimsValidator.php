<?php
/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\clock\Clock;
use actra\yuf\clock\SystemClock;
use actra\yuf\exception\UnauthorizedException;
use stdClass;

/**
 * Checks the time claims (`nbf`, `iat`, `exp`) of a decoded ID token against the clock, with a leeway for clock skew.
 */
final readonly class IdTokenTimeClaimsValidator
{
    public function __construct(private Clock $clock = new SystemClock(), private int $leewayInSeconds = 60)
    {
    }

    /**
     * @throws UnauthorizedException If a claim is missing, not yet valid or expired
     */
    public function assertValid(stdClass $payload): void
    {
        $timestamp = $this->clock->now()->getTimestamp();
        if (!property_exists(object_or_class: $payload, property: 'nbf') || $payload->nbf > ($timestamp + $this->leewayInSeconds)) {
            throw new UnauthorizedException(message: 'Missing or outdated nbf');
        }
        if (!property_exists(object_or_class: $payload, property: 'iat') || $payload->iat > ($timestamp + $this->leewayInSeconds)) {
            throw new UnauthorizedException(message: 'Missing or outdated iat');
        }
        if (!property_exists(object_or_class: $payload, property: 'exp') || ($timestamp - $this->leewayInSeconds) >= $payload->exp) {
            throw new UnauthorizedException(message: 'Missing or expired exp');
        }
    }
}