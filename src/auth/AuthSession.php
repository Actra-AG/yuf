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
    private const string isLoggedInIndicator = 'isLoggedIn';
    private const string authSessionIdIndicator = 'authSessionID';

    final public static function logIn(int $authSessionID): void
    {
        AuthSession::setIsLoggedIn(isLoggedIn: true);
        AuthSession::setAuthSessionID(authSessionID: $authSessionID);
    }

    private static function setIsLoggedIn(bool $isLoggedIn): void
    {
        AuthSession::saveToSession(key: AuthSession::isLoggedInIndicator, value: $isLoggedIn);
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

    private static function setAuthSessionID(int $authSessionID): void
    {
        AuthSession::saveToSession(key: AuthSession::authSessionIdIndicator, value: $authSessionID);
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
        $isLoggedIn = AuthSession::readSessionData()[AuthSession::isLoggedInIndicator] ?? null;
        if (!is_bool(value: $isLoggedIn)) {
            AuthSession::setIsLoggedIn(isLoggedIn: false);

            return false;
        }

        return $isLoggedIn;
    }

    private static function resetSession(): void
    {
        AbstractSessionHandler::clearUserData();
        AuthSession::setIsLoggedIn(isLoggedIn: false);
        AuthSession::setAuthSessionID(authSessionID: 0);
        AbstractSessionHandler::getSessionHandler()->regenerateID();
    }

    final public static function getAuthSessionID(): int
    {
        $authSessionID = AuthSession::readSessionData()[AuthSession::authSessionIdIndicator] ?? null;
        if (!is_int(value: $authSessionID)) {
            throw new UnexpectedValueException(message: 'The session contains no auth session ID.');
        }

        return $authSessionID;
    }
}