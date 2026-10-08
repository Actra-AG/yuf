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
use InvalidArgumentException;
use stdClass;

/**
 * Checks the time claims (`nbf`, `iat`, `exp`) of a decoded ID token against the clock, with a leeway for clock skew.
 * All three claims are required and must be numbers (seconds since 1970).
 */
final readonly class IdTokenTimeClaimsValidator
{
    /**
     * @throws InvalidArgumentException if the leeway is negative
     */
    public function __construct(private Clock $clock = new SystemClock(), private int $leewayInSeconds = 60)
    {
        if ($leewayInSeconds < 0) {
            throw new InvalidArgumentException(message: 'The leeway must not be negative.');
        }
    }

    /**
     * @throws UnauthorizedException If a claim is missing, not a number, not yet valid or expired
     */
    public function assertValid(stdClass $payload): void
    {
        $timestamp = $this->clock->now()->getTimestamp();
        $notBefore = IdTokenTimeClaimsValidator::readClaim(payload: $payload, name: 'nbf');
        if ($notBefore === null || $notBefore > $timestamp + $this->leewayInSeconds) {
            throw new UnauthorizedException(message: 'Missing or outdated nbf');
        }
        $issuedAt = IdTokenTimeClaimsValidator::readClaim(payload: $payload, name: 'iat');
        if ($issuedAt === null || $issuedAt > $timestamp + $this->leewayInSeconds) {
            throw new UnauthorizedException(message: 'Missing or outdated iat');
        }
        $expires = IdTokenTimeClaimsValidator::readClaim(payload: $payload, name: 'exp');
        if ($expires === null || $timestamp - $this->leewayInSeconds >= $expires) {
            throw new UnauthorizedException(message: 'Missing or expired exp');
        }
    }

    private static function readClaim(stdClass $payload, string $name): int|float|null
    {
        if (!property_exists(object_or_class: $payload, property: $name)) {
            return null;
        }
        $value = $payload->$name;

        return is_int(value: $value) || is_float(value: $value) ? $value : null;
    }
}
