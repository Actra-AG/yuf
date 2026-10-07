<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\session\AbstractSessionHandler;
use UnexpectedValueException;

class AuthSession
{
    private const string SESSION_KEY = 'auth_userSession';
    private const string IS_LOGGED_IN_INDICATOR = 'isLoggedIn';
    private const string AUTH_SESSION_ID_INDICATOR = 'authSessionId';

    final public static function logIn(int $authSessionId): void
    {
        AuthSession::setIsLoggedIn(isLoggedIn: true);
        AuthSession::setAuthSessionId(authSessionId: $authSessionId);
    }

    private static function setIsLoggedIn(bool $isLoggedIn): void
    {
        AuthSession::saveToSession(key: AuthSession::IS_LOGGED_IN_INDICATOR, value: $isLoggedIn);
    }

    private static function saveToSession(string $key, bool|int $value): void
    {
        $authSessionData = AuthSession::readSessionData();
        $authSessionData[$key] = $value;
        $_SESSION[AuthSession::SESSION_KEY] = $authSessionData;
    }

    /**
     * @return array<mixed>
     */
    private static function readSessionData(): array
    {
        $authSessionData = $_SESSION[AuthSession::SESSION_KEY] ?? null;

        return is_array(value: $authSessionData) ? $authSessionData : [];
    }

    private static function setAuthSessionId(int $authSessionId): void
    {
        AuthSession::saveToSession(key: AuthSession::AUTH_SESSION_ID_INDICATOR, value: $authSessionId);
    }

    /**
     * Logs the user out and removes all data of the user from the session (see
     * AbstractSessionHandler::clearUserData()). Does nothing if no user is logged in.
     */
    final public static function logOut(): void
    {
        if (!AuthSession::isLoggedIn()) {
            return;
        }
        AuthSession::resetSession();
    }

    final public static function isLoggedIn(): bool
    {
        $isLoggedIn = AuthSession::readSessionData()[AuthSession::IS_LOGGED_IN_INDICATOR] ?? null;
        if (!is_bool(value: $isLoggedIn)) {
            AuthSession::setIsLoggedIn(isLoggedIn: false);

            return false;
        }
        if (!$isLoggedIn) {
            return false;
        }
        // A session of yuf before v4.16.0 stored the ID under "authSessionID": such a user has to log in again
        if (!is_int(value: AuthSession::readSessionData()[AuthSession::AUTH_SESSION_ID_INDICATOR] ?? null)) {
            AuthSession::resetSession();

            return false;
        }

        return true;
    }

    private static function resetSession(): void
    {
        AbstractSessionHandler::clearUserData();
        AuthSession::setIsLoggedIn(isLoggedIn: false);
        AuthSession::setAuthSessionId(authSessionId: 0);
        AbstractSessionHandler::getSessionHandler()->regenerateId();
    }

    final public static function getAuthSessionId(): int
    {
        $authSessionId = AuthSession::readSessionData()[AuthSession::AUTH_SESSION_ID_INDICATOR] ?? null;
        if (!is_int(value: $authSessionId)) {
            throw new UnexpectedValueException(message: 'The session contains no auth session ID.');
        }

        return $authSessionId;
    }
}
