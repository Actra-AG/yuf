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
 * writes the log of login attempts (`logAuthResult()`) and adds own login methods that call `doLogin()`.
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
        if ($this->authResult !== AuthResultEnum::UNDEFINED) {
            throw new LogicException(message: 'It is not allowed to execute this method multiple times.');
        }
        if ($this->authSession->isLoggedIn()) {
            throw new LogicException(message: 'It is not allowed to log in, if user is already logged in.');
        }
        $attempt = new LoginAttempt(
            sessionId: $this->authSession->getSessionId(),
            ipAddress: $this->httpRequest->getRemoteAddress(),
            userName: $userName,
        );
        $authUser = $this->createAuthUserByUserName(userName: $userName);
        if ($authUser === null) {
            if ($passwordToCheck !== null) {
                Password::spendVerificationTime(rawPassword: $passwordToCheck);
            }

            return $this->reject(
                authResult: AuthResultEnum::ERROR_UNKNOWN_USER_NAME,
                attempt: $attempt,
                userId: null,
            );
        }
        $rejection = $this->findRejection(authUser: $authUser, passwordToCheck: $passwordToCheck, attempt: $attempt);
        if ($rejection !== null) {
            return $this->reject(authResult: $rejection, attempt: $attempt, userId: $authUser->id);
        }
        if ($passwordToCheck !== null && $authUser->password->needsRehash()) {
            $authUser->rehashPassword(rawPassword: $passwordToCheck);
        }
        $this->authResult = $authMethod->getSuccessAuthResult();
        $this->log(attempt: $attempt, userId: $authUser->id);
        $this->authSession->logIn(authSessionId: $authUser->confirmSuccessfulLogin());

        return true;
    }

    /**
     * @return ?AuthResultEnum Why the login is refused, `null` if the user may log in
     */
    private function findRejection(
        AuthUser $authUser,
        #[SensitiveParameter]
        ?string $passwordToCheck,
        LoginAttempt $attempt,
    ): ?AuthResultEnum {
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
        if ($passwordToCheck === null) {
            return null;
        }
        if (!$authUser->hasOneOfRights(
            accessRightCollection: AccessRightCollection::createFromStringArray(
                input: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
            ),
        )) {
            return AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE;
        }
        if (!$authUser->password->isValid(rawPassword: $passwordToCheck)) {
            $authUser->increaseWrongPasswordAttempts();

            return AuthResultEnum::ERROR_WRONG_PASSWORD;
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

    private function reject(AuthResultEnum $authResult, LoginAttempt $attempt, ?int $userId): false
    {
        $this->authResult = $authResult;
        $this->log(attempt: $attempt, userId: $userId);

        return false;
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
