<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

/**
 * Sends the cookie of the SSO nonce (see `SsoNonceCookie`): `Secure`, `HttpOnly`, `SameSite=None` (the identity
 * provider returns with a form post from another site) and the given path. A seam for tests; the default is
 * `NativeSsoNonceCookieWriter`.
 */
interface SsoNonceCookieWriter
{
    public function set(string $name, string $value, int $maxAgeInSeconds, string $path): void;

    /**
     * Expires the cookie of the given path.
     */
    public function delete(string $name, string $path): void;
}
