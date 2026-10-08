<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\session\Session;
use actra\yuf\session\SessionSectionEnum;
use LogicException;

/**
 * The login state of the user, kept in the session (`yuf.auth`). `Core` hands one to the views
 * (`ViewContext::$authSession`) and to the `Authenticator`.
 */
final readonly class AuthSession
{
    public function __construct(private Session $session) {}

    public function logIn(int $authSessionId): void
    {
        $this->session->setSection(
            section: SessionSectionEnum::AUTH,
            data: [
                AuthSessionKeyEnum::IS_LOGGED_IN->value => true,
                AuthSessionKeyEnum::AUTH_SESSION_ID->value => $authSessionId,
            ],
        );
    }

    /**
     * Logs the user out and removes all data of the user from the session (see `Session::clearUserData()`), with a new
     * session ID. Does nothing if no user is logged in.
     */
    public function logOut(): void
    {
        if (!$this->isLoggedIn()) {
            return;
        }
        $this->resetSession();
    }

    public function isLoggedIn(): bool
    {
        $authData = $this->session->getSection(section: SessionSectionEnum::AUTH);
        if (($authData[AuthSessionKeyEnum::IS_LOGGED_IN->value] ?? null) !== true) {
            return false;
        }
        // Incomplete login state: fail closed, the user has to log in again
        if (!is_int(value: $authData[AuthSessionKeyEnum::AUTH_SESSION_ID->value] ?? null)) {
            $this->resetSession();

            return false;
        }

        return true;
    }

    /**
     * The session ID, e.g. for the log of login attempts.
     */
    public function getSessionId(): string
    {
        return $this->session->getId();
    }

    /**
     * @throws LogicException if no user is logged in
     */
    public function getAuthSessionId(): int
    {
        $isLoggedIn = $this->isLoggedIn();
        $authSessionId = $this->session->getSection(
            section: SessionSectionEnum::AUTH,
        )[AuthSessionKeyEnum::AUTH_SESSION_ID->value] ?? null;
        if (!$isLoggedIn || !is_int(value: $authSessionId)) {
            throw new LogicException(message: 'No user is logged in: there is no auth session ID.');
        }

        return $authSessionId;
    }

    private function resetSession(): void
    {
        $this->session->clearUserData();
        $this->session->regenerateId();
    }
}
