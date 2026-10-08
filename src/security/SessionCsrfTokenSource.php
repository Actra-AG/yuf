<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\security;

use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use Override;

/**
 * The CSRF token of the user's session (`yuf.csrf.token`): 32 random bytes, base64 encoded, the same token for the
 * whole session (until `renew()` or `Session::clearUserData()`). The session is only touched when a token is requested
 * or checked. An empty or non-string stored value counts as missing; a posted token is never valid without a stored
 * one (fail closed).
 */
final readonly class SessionCsrfTokenSource implements CsrfTokenSource
{
    private const string TOKEN_KEY = 'token';

    public function __construct(private Session $session) {}

    #[Override]
    public function getToken(): string
    {
        $token = $this->readToken();
        if ($token !== null) {
            return $token;
        }

        return $this->renew();
    }

    #[Override]
    public function isValid(string $token): bool
    {
        $storedToken = $this->readToken();

        return $storedToken !== null && hash_equals(known_string: $storedToken, user_string: $token);
    }

    /**
     * Replaces the token of the session with a new one, e.g. after a privilege change.
     */
    public function renew(): string
    {
        $token = base64_encode(string: random_bytes(length: 32));
        $this->session->setSection(
            section: SessionSectionEnum::CSRF,
            data: [SessionCsrfTokenSource::TOKEN_KEY => $token],
        );

        return $token;
    }

    private function readToken(): ?string
    {
        $csrfData = $this->session->getSection(section: SessionSectionEnum::CSRF);
        if (!array_key_exists(key: SessionCsrfTokenSource::TOKEN_KEY, array: $csrfData)) {
            return null;
        }
        $token = $csrfData[SessionCsrfTokenSource::TOKEN_KEY];

        return is_string(value: $token) && $token !== '' ? $token : null;
    }
}
