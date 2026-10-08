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

    public function passwordLogin(string $userName, string $inputPassword): bool
    {
        return $this->doLogin(
            authMethod: AuthMethodEnum::PASSWORD,
            userName: $userName,
            passwordToCheck: $inputPassword,
        );
    }

    protected function doLogin(
        AuthMethodEnum $authMethod,
        string $userName,
        ?string $passwordToCheck,
    ): bool {
        if ($this->authResult !== AuthResultEnum::UNDEFINED) {
            throw new LogicException(message: 'It is not allowed to execute this method multiple times.');
        }
        if ($this->authSession->isLoggedIn()) {
            throw new LogicException(message: 'It is not allowed to log in, if user is already logged in.');
        }
        $sessionId = $this->authSession->getSessionId();
        $ipAddress = $this->httpRequest->getRemoteAddress();
        $authUser = $this->createAuthUserByUserName(userName: $userName);
        if ($authUser === null) {
            $this->authResult = AuthResultEnum::ERROR_UNKNOWN_USER_NAME;
            $this->logAuthResult(
                userId: null,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $userName,
                authResult: $this->authResult,
            );

            return false;
        }
        $userId = $authUser->id;
        if ($authUser->ipWhitelist !== []
            && !IpValidator::isInWhitelist(
                whiteList: $authUser->ipWhitelist,
                ipAddressToCheck: $ipAddress,
            )
        ) {
            $this->authResult = AuthResultEnum::ERROR_IP_NOT_ALLOWED;
            $this->logAuthResult(
                userId: $userId,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $userName,
                authResult: $this->authResult,
            );

            return false;
        }
        if (!$this->checkLoginCredentials(authUser: $authUser)) {
            if ($this->authResult === AuthResultEnum::UNDEFINED) {
                throw new LogicException(message: 'Undefined authResult');
            }
            $this->logAuthResult(
                userId: $userId,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $userName,
                authResult: $this->authResult,
            );

            return false;
        }
        if (!$authUser->isActive) {
            $this->authResult = AuthResultEnum::ERROR_INACTIVE;
            $this->logAuthResult(
                userId: $userId,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $userName,
                authResult: $this->authResult,
            );

            return false;
        }
        if ($authUser->wrongPasswordAttempts >= $this->maxAllowedWrongPasswordAttempts) {
            $this->authResult = AuthResultEnum::ERROR_OUT_TRIED;
            $this->logAuthResult(
                userId: $userId,
                sessionId: $sessionId,
                ip: $ipAddress,
                userName: $userName,
                authResult: $this->authResult,
            );

            return false;
        }
        if ($passwordToCheck !== null) {
            if (!$authUser->hasOneOfRights(
                accessRightCollection: AccessRightCollection::createFromStringArray(
                    input: [AccessRightCollection::ACCESS_DO_PASSWORD_LOGIN],
                ),
            )
            ) {
                $this->authResult = AuthResultEnum::ERROR_NO_PASSWORD_LOGIN_ACTIVE;
                $this->logAuthResult(
                    userId: $userId,
                    sessionId: $sessionId,
                    ip: $ipAddress,
                    userName: $userName,
                    authResult: $this->authResult,
                );

                return false;
            }
            if (!$authUser->password->isValid(rawPassword: $passwordToCheck)) {
                $authUser->increaseWrongPasswordAttempts();
                $this->authResult = AuthResultEnum::ERROR_WRONG_PASSWORD;
                $this->logAuthResult(
                    userId: $userId,
                    sessionId: $sessionId,
                    ip: $ipAddress,
                    userName: $userName,
                    authResult: $this->authResult,
                );

                return false;
            }
        }
        $this->authResult = $authMethod->getSuccessAuthResult();
        $this->logAuthResult(
            userId: $userId,
            sessionId: $sessionId,
            ip: $ipAddress,
            userName: $userName,
            authResult: $this->authResult,
        );
        $this->authSession->logIn(authSessionId: $authUser->confirmSuccessfulLogin());

        return true;
    }

    abstract protected function createAuthUserByUserName(string $userName): ?AuthUser;

    abstract protected function logAuthResult(
        ?int $userId,
        string $sessionId,
        string $ip,
        string $userName,
        AuthResultEnum $authResult,
    ): void;

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
