<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\auth;

use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\AuthUser;
use actra\yuf\auth\MicrosoftAuthenticator;
use actra\yuf\core\HttpRequest;
use actra\yuf\session\AbstractSessionHandler;
use Override;

/**
 * A Microsoft login with one known user; exposes the token login.
 */
final class TestMicrosoftAuthenticator extends MicrosoftAuthenticator
{
    /** @var list<AuthResultEnum> */
    public array $loggedResults = [];

    public function __construct(
        HttpRequest $httpRequest,
        AuthSession $authSession,
        AbstractSessionHandler $sessionHandler,
        string $logDirectory,
        string $cacheDirectory,
        private readonly ?AuthUser $authUser,
    ) {
        parent::__construct(
            httpRequest: $httpRequest,
            authSession: $authSession,
            maxAllowedWrongPasswordAttempts: 3,
            sessionHandler: $sessionHandler,
            logDirectory: $logDirectory,
            cacheDirectory: $cacheDirectory,
        );
    }

    public function login(string $tenantId, string $clientId, string $nonce, string $idToken): bool
    {
        return $this->microsoftIdTokenLogin(
            tenantId: $tenantId,
            clientId: $clientId,
            ssoNonce: $nonce,
            microsoftIdToken: $idToken,
        );
    }

    #[Override]
    protected function createAuthUserByUserName(string $userName): ?AuthUser
    {
        return $userName === 'user@example.org' ? $this->authUser : null;
    }

    #[Override]
    protected function logAuthResult(
        ?int $userId,
        string $sessionId,
        string $ip,
        string $userName,
        AuthResultEnum $authResult,
    ): void {
        $this->loggedResults[] = $authResult;
    }

    #[Override]
    protected function checkLoginCredentials(AuthUser $authUser): bool
    {
        return true;
    }
}
