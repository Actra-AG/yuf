<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use Override;

/**
 * The CSRF token of the user's session (see `CsrfToken`). The session is only touched when a token is requested or
 * checked.
 */
final readonly class SessionCsrfTokenSource implements CsrfTokenSource
{
    #[Override]
    public function getToken(): string
    {
        return CsrfToken::getToken();
    }

    #[Override]
    public function isValid(string $token): bool
    {
        return CsrfToken::validateToken(token: $token);
    }
}
