<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use Override;

/**
 * Sends the cookie with `setcookie()`, which adds a header next to the session cookie (it does not replace it). Not
 * unit tested (it sends headers of the test process).
 */
final readonly class NativeSsoNonceCookieWriter implements SsoNonceCookieWriter
{
    #[Override]
    public function set(string $name, string $value, int $maxAgeInSeconds, string $path): void
    {
        setcookie(
            name: $name,
            value: $value,
            expires_or_options: [
                'expires' => time() + $maxAgeInSeconds,
                'path' => $path,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
            ],
        );
    }

    #[Override]
    public function delete(string $name, string $path): void
    {
        setcookie(
            name: $name,
            value: '',
            expires_or_options: [
                'expires' => 1,
                'path' => $path,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'None',
            ],
        );
    }
}
