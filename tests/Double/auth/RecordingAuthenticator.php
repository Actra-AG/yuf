<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Double\auth;

use actra\yuf\auth\Authenticator;
use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\AuthUser;
use actra\yuf\core\HttpRequest;
use Override;

/**
 * Records the arguments of `logAuthResult()`. Authenticator allows one instance only (see `AuthenticatorTest`).
 */
final class RecordingAuthenticator extends Authenticator
{
    /**
     * @var list<array{userId: ?int, sessionId: string, ip: string, userName: string, authResult: AuthResultEnum}>
     */
    public array $loggedResults = [];

    public function __construct(
        HttpRequest $httpRequest,
        AuthSession $authSession,
        private readonly ?AuthUser $authUser,
    ) {
        parent::__construct(httpRequest: $httpRequest, authSession: $authSession, maxAllowedWrongPasswordAttempts: 3);
    }

    #[Override]
    protected function createAuthUserByUserName(string $userName): ?AuthUser
    {
        return $this->authUser;
    }

    #[Override]
    protected function logAuthResult(
        ?int $userId,
        string $sessionId,
        string $ip,
        string $userName,
        AuthResultEnum $authResult,
    ): void {
        $this->loggedResults[] = [
            'userId' => $userId,
            'sessionId' => $sessionId,
            'ip' => $ip,
            'userName' => $userName,
            'authResult' => $authResult,
        ];
    }

    #[Override]
    protected function checkLoginCredentials(AuthUser $authUser): bool
    {
        return true;
    }
}
