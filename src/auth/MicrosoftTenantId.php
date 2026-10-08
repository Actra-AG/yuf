<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use InvalidArgumentException;

/**
 * The tenant ID goes into URLs and file names: only a GUID or a domain name is accepted.
 *
 * @internal
 */
final readonly class MicrosoftTenantId
{
    /**
     * @throws InvalidArgumentException
     */
    public static function assertValid(string $tenantId): void
    {
        if (preg_match(pattern: '/^[A-Za-z0-9][A-Za-z0-9.-]*$/D', subject: $tenantId) !== 1) {
            throw new InvalidArgumentException(
                message: 'The Microsoft tenant ID must be a GUID or a domain name (letters, digits, "." and "-").',
            );
        }
    }
}
