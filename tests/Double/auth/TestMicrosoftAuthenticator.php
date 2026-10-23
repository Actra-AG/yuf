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
use actra\yuf\core\ResponseSender;
use Override;

/**
 * A Microsoft login with one known user; exposes the token login.
 */
final class TestMicrosoftAuthenticator extends MicrosoftAuthenticator
{
    /** @var list<AuthResultEnum> */
    public array $loggedResults = [];
    /** @var list<list<string>> */
    public array $lookups = [];

    public function __construct(
        HttpRequest $httpRequest,
        AuthSession $authSession,
        string $logDirectory,
        string $cacheDirectory,
        private readonly ?AuthUser $authUser,
        RecordingSsoNonceCookieWriter $nonceCookieWriter,
        private readonly string $knownObjectId = '99999999-8888-7777-6666-555555555555',
        private readonly string $knownTenantId = '11111111-2222-3333-4444-555555555555',
    ) {
        parent::__construct(
            httpRequest: $httpRequest,
            authSession: $authSession,
            maxAllowedWrongPasswordAttempts: 3,
            loginRateLimit: null,
            logDirectory: $logDirectory,
            cacheDirectory: $cacheDirectory,
            nonceCookieWriter: $nonceCookieWriter,
        );
    }

    public function login(string $tenantId, string $clientId, string $redirectUri, string $idToken): bool
    {
        return $this->microsoftIdTokenLogin(
            tenantId: $tenantId,
            clientId: $clientId,
            redirectUri: $redirectUri,
            microsoftIdToken: $idToken,
        );
    }

    public function redirect(
        string $tenantId,
        string $clientId,
        string $redirectUri,
        ResponseSender $responseSender,
    ): never {
        $this->redirectToMicrosoftLogin(
            tenantId: $tenantId,
            clientId: $clientId,
            redirectUri: $redirectUri,
            responseSender: $responseSender,
        );
    }

    #[Override]
    protected function findAuthUserByMicrosoftAccount(string $objectId, string $tenantId): ?AuthUser
    {
        $this->lookups[] = [$objectId, $tenantId];

        return $objectId === $this->knownObjectId && $tenantId === $this->knownTenantId ? $this->authUser : null;
    }

    #[Override]
    protected function createAuthUserByUserName(string $userName): ?AuthUser
    {
        // The email address of a token is never used to find a user
        $this->lookups[] = [$userName];

        return null;
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
