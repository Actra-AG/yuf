<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\core\HttpRequest;
use actra\yuf\datacheck\validatorTypes\IpValidator;
use LogicException;
use SensitiveParameter;

/**
 * Extension point: the login of a user. A project class extends it and loads the user (`createAuthUserByUserName()`),
 * writes the log of login attempts (`logAuthResult()`) and adds own login methods that call `doLogin()`. A login with a
 * second step verifies first (`verifyPassword()`, `verifyCredentials()`) and logs in later (`logInVerifiedUser()`).
 *
 * `$authResult` tells the project why a login failed (log, statistics). Show the user one and the same message for an
 * unknown user and a wrong password (and do not reveal a lock-out to anyone but the owner), otherwise the login tells
 * which user names exist. Wrong passwords are counted per user (`AuthUser::$wrongPasswordAttempts`); a user with
 * `$maxAllowedWrongPasswordAttempts` wrong attempts is locked out until the project resets the counter, even with
 * the right password.
 */
abstract class Authenticator
{
    public protected(set) AuthResultEnum $authResult = AuthResultEnum::UNDEFINED;

    /**
     * @param HttpRequest $httpRequest The request of the login: its remote address is checked against the IP
     *                                 whitelist of the user and logged (`Core::$httpRequest`)
     * @param AuthSession $authSession Where the login is stored (`ViewContext::$authSession`)
     */
    protected function __construct(
        protected readonly HttpRequest $httpRequest,
        protected readonly AuthSession $authSession,
        private readonly int $maxAllowedWrongPasswordAttempts,
    ) {}

    public function passwordLogin(string $userName, #[SensitiveParameter] string $inputPassword): bool
    {
        return $this->doLogin(
            authMethod: AuthMethodEnum::PASSWORD,
            userName: $userName,
            passwordToCheck: $inputPassword,
        );
    }

    /**
     * Verifies the password of the user (every check of `verifyCredentials()`, with the password login method) without
     * logging in: for a login form with a second step (a two-factor code, a confirmation). The caller logs the user in
     * after the second step with its own method that calls `logInVerifiedUser()`; see docs/session-and-login.md.
     *
     * @return AuthResultEnum|AuthUser The reason of the rejection, or the user whose password is right
     *
     * @throws LogicException see `verifyCredentials()`
     */
    public function verifyPassword(
        string $userName,
        #[SensitiveParameter]
        string $inputPassword,
    ): AuthResultEnum|AuthUser {
        return $this->verifyCredentials(
            authMethod: AuthMethodEnum::PASSWORD,
            userName: $userName,
            password: $inputPassword,
        );
    }

    /**
     * May this user get a token (password reset, login link, one-time code)? Checks the existence of the user, the IP
     * whitelist, the check of the project (`checkLoginCredentials()`), the state (inactive) and the lock-out; no
     * password and no counting of wrong attempts. Rejections are logged like those of a login (`logAuthResult()`), so
     * a flood of token requests stays visible; a success is not logged (nothing happened yet) and does not change
     * `$authResult`.
     *
     * It is public: a form that sends a token is the caller, and it cannot log anybody in. Answer the user with the
     * same message for every rejection, otherwise the form tells which user names exist (an unknown user is answered
     * faster than a known one, as no password is hashed here: send the token after the response, see
     * `ResponseSender::afterResponse()`).
     *
     * @return AuthResultEnum|AuthUser The reason of the rejection, or the user
     *
     * @throws LogicException if this authenticator rejected a user or logged one in already, or the user is already
     *     logged in
     */
    public function precheck(string $userName): AuthResultEnum|AuthUser
    {
        $this->assertNoLoginTried();
        $attempt = $this->createAttempt(userName: $userName);
        $authUser = $this->createAuthUserByUserName(userName: $userName);
        if ($authUser === null) {
            return $this->reject(authResult: AuthResultEnum::ERROR_UNKNOWN_USER_NAME, attempt: $attempt, userId: null);
        }
        $rejection = $this->findStateRejection(authUser: $authUser, attempt: $attempt);

        return $rejection === null
            ? $authUser
            : $this->reject(authResult: $rejection, attempt: $attempt, userId: $authUser->id);
    }

    /**
     * Every check of a login without logging in: the user exists, the IP whitelist, the check of the project, the
     * state (inactive), the lock-out and, with a password, the password itself (a wrong one is counted; an unknown user
     * or a user without password costs the time of a verification and is not counted). A password hash that needs an
     * upgrade is rehashed. Rejections are logged (`logAuthResult()`); a success is not (see `logInVerifiedUser()`).
     *
     * It is protected: with `null` as password the password is not checked (one-time token, identity provider), so a
     * public method with this signature would let a view skip the password by mistake. Projects expose public methods
     * with a fixed method (`verifyPassword()`).
     *
     * @param ?string $password The password to verify, `null` for methods that were verified elsewhere
     *
     * @return AuthResultEnum|AuthUser The reason of the rejection (`$authResult` has it too), or the verified user
     *
     * @throws LogicException if this authenticator rejected a user or logged one in already, or the user is already
     *     logged in
     */
    protected function verifyCredentials(
        AuthMethodEnum $authMethod,
        string $userName,
        #[SensitiveParameter]
        ?string $password,
    ): AuthResultEnum|AuthUser {
        $this->assertNoLoginTried();

        return $this->verify(
            attempt: $this->createAttempt(userName: $userName),
            password: $password,
        );
    }

    /**
     * Logs in a user whose credentials were verified in an earlier request or step (`verifyCredentials()` and a second
     * factor of the project): logs the success, calls `AuthUser::confirmSuccessfulLogin()` and
     * `AuthSession::logIn()`, `$authResult` is the success result of the method.
     *
     * It is protected and not a way around the checks: the given user is checked again against everything that can
     * change between the steps (IP whitelist, check of the project, inactive, lock-out; a rejection is logged and
     * returned as `false`, `$authResult` has the reason), so a user deactivated or locked out after the first step does
     * not get in. What it cannot know is whether the caller verified the password and the second factor: call it only
     * from a project method that did, never with a user from `createAuthUserByUserName()` that nobody verified. Load
     * the user again from the ID stored in the session (do not keep the object), and delete the pending state at
     * once, so a second factor cannot be used twice.
     *
     * @param string $userName Written to the log of the attempt
     *
     * @throws LogicException if this authenticator rejected a user or logged one in already, or the user is already
     *     logged in
     */
    protected function logInVerifiedUser(AuthMethodEnum $authMethod, AuthUser $authUser, string $userName): bool
    {
        $this->assertNoLoginTried();
        $attempt = $this->createAttempt(userName: $userName);
        $rejection = $this->findStateRejection(authUser: $authUser, attempt: $attempt);
        if ($rejection !== null) {
            $this->reject(authResult: $rejection, attempt: $attempt, userId: $authUser->id);

            return false;
        }
        $this->completeLogin(authMethod: $authMethod, authUser: $authUser, attempt: $attempt);

        return true;
    }

    /**
     * Verifies the credentials and logs the user in: `verifyCredentials()`, the log of the success and
     * `AuthSession::logIn()`.
     *
     * @param ?string $passwordToCheck The password to verify, `null` for login methods that were verified elsewhere
     *     (one-time token, identity provider)
     *
     * @throws LogicException if this authenticator already tried a login or the user is already logged in
     */
    protected function doLogin(
        AuthMethodEnum $authMethod,
        string $userName,
        #[SensitiveParameter]
        ?string $passwordToCheck,
    ): bool {
        $this->assertNoLoginTried();
        $attempt = $this->createAttempt(userName: $userName);
        $verified = $this->verify(attempt: $attempt, password: $passwordToCheck);
        if ($verified instanceof AuthResultEnum) {
            return false;
        }
        $this->completeLogin(authMethod: $authMethod, authUser: $verified, attempt: $attempt);

        return true;
    }

    /**
     * @throws LogicException
     */
    private function assertNoLoginTried(): void
    {
        if ($this->authResult !== AuthResultEnum::UNDEFINED) {
            throw new LogicException(message: 'It is not allowed to execute this method multiple times.');
        }
        if ($this->authSession->isLoggedIn()) {
            throw new LogicException(message: 'It is not allowed to log in, if user is already logged in.');
        }
    }

    private function createAttempt(string $userName): LoginAttempt
    {
        return new LoginAttempt(
            sessionId: $this->authSession->getSessionId(),
            ipAddress: $this->httpRequest->getRemoteAddress(),
            userName: $userName,
        );
    }

    /**
     * @return AuthResultEnum|AuthUser The rejection (set and logged), or the verified user
     */
    private function verify(
        LoginAttempt $attempt,
        #[SensitiveParameter]
        ?string $password,
    ): AuthResultEnum|AuthUser {
        $authUser = $this->createAuthUserByUserName(userName: $attempt->userName);
        if ($authUser === null) {
            if ($password !== null) {
                Password::spendVerificationTime(rawPassword: $password);
            }

            return $this->reject(authResult: AuthResultEnum::ERROR_UNKNOWN_USER_NAME, attempt: $attempt, userId: null);
        }
        $rejection = $this->findRejection(authUser: $authUser, passwordToCheck: $password, attempt: $attempt);
        if ($rejection !== null) {
            return $this->reject(authResult: $rejection, attempt: $attempt, userId: $authUser->id);
        }
        if ($password !== null && $authUser->password?->needsRehash() === true) {
            $authUser->rehashPassword(rawPassword: $password);
        }

        return $authUser;
    }

    private function completeLogin(AuthMethodEnum $authMethod, AuthUser $authUser, LoginAttempt $attempt): void
    {
        $this->authResult = $authMethod->getSuccessAuthResult();
        $this->log(attempt: $attempt, userId: $authUser->id);
        $this->authSession->logIn(authSessionId: $authUser->confirmSuccessfulLogin());
    }

    /**
     * @return ?AuthResultEnum Why the user is refused, `null` if the user may log in
     */
    private function findRejection(
        AuthUser $authUser,
        #[SensitiveParameter]
        ?string $passwordToCheck,
        LoginAttempt $attempt,
    ): ?AuthResultEnum {
        $stateRejection = $this->findStateRejection(authUser: $authUser, attempt: $attempt);
        if ($stateRejection !== null) {
            return $stateRejection;
        }
        if ($passwordToCheck === null) {
            return null;
        }
        $storedPassword = $authUser->password;
        if ($storedPassword === null) {
            // Costs the time of a verification, so the answer time does not tell which users have no password
            Password::spendVerificationTime(rawPassword: $passwordToCheck);

            return AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE;
        }
        if (!$storedPassword->isValid(rawPassword: $passwordToCheck)) {
            $authUser->increaseWrongPasswordAttempts();

            return AuthResultEnum::ERROR_WRONG_PASSWORD;
        }

        return null;
    }

    /**
     * The checks that need no password: IP whitelist, check of the project, inactive, lock-out.
     *
     * @return ?AuthResultEnum Why the user is refused, `null` if the user may go on
     */
    private function findStateRejection(AuthUser $authUser, LoginAttempt $attempt): ?AuthResultEnum
    {
        if ($authUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(whiteList: $authUser->ipWhitelist, ipAddressToCheck: $attempt->ipAddress)) {
            return AuthResultEnum::ERROR_IP_NOT_ALLOWED;
        }
        if (!$this->checkLoginCredentials(authUser: $authUser)) {
            return $this->getResultOfFailedCredentialCheck();
        }
        if (!$authUser->isActive) {
            return AuthResultEnum::ERROR_INACTIVE;
        }
        if ($authUser->wrongPasswordAttempts >= $this->maxAllowedWrongPasswordAttempts) {
            return AuthResultEnum::ERROR_OUT_TRIED;
        }

        return null;
    }

    /**
     * `checkLoginCredentials()` of the project sets `$authResult` when it refuses.
     */
    private function getResultOfFailedCredentialCheck(): AuthResultEnum
    {
        $authResult = $this->authResult;
        if ($authResult === AuthResultEnum::UNDEFINED) {
            throw new LogicException(message: 'checkLoginCredentials() returned false without setting authResult.');
        }

        return $authResult;
    }

    private function reject(AuthResultEnum $authResult, LoginAttempt $attempt, ?int $userId): AuthResultEnum
    {
        $this->authResult = $authResult;
        $this->log(attempt: $attempt, userId: $userId);

        return $authResult;
    }

    private function log(LoginAttempt $attempt, ?int $userId): void
    {
        $this->logAuthResult(
            userId: $userId,
            sessionId: $attempt->sessionId,
            ip: $attempt->ipAddress,
            userName: $attempt->userName,
            authResult: $this->authResult,
        );
    }

    abstract protected function createAuthUserByUserName(string $userName): ?AuthUser;

    /**
     * Writes the log of a login attempt. Never log the password; the session ID is the one before the login.
     */
    abstract protected function logAuthResult(
        ?int $userId,
        string $sessionId,
        string $ip,
        string $userName,
        AuthResultEnum $authResult,
    ): void;

    /**
     * Additional check of the project (e.g. a second factor). Sets `$authResult` and returns `false` to refuse.
     */
    abstract protected function checkLoginCredentials(AuthUser $authUser): bool;

    protected function authWebTokenLogin(
        AuthMethodEnum $authMethod,
        AuthWebToken $authWebToken,
    ): bool {
        return $this->doLogin(
            authMethod: $authMethod,
            userName: $authWebToken->getUserName(),
            passwordToCheck: null,
        );
    }
}
