<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\auth;

use actra\yuf\common\LogFile;
use actra\yuf\core\HttpRequest;
use actra\yuf\core\HttpResponse;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\session\AbstractSessionHandler;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Extension point: the login with a Microsoft account (OpenID Connect). A project class extends it, redirects the user
 * with `redirectToMicrosoftLogin()` and passes the posted ID token to `microsoftIdTokenLogin()`. A token that does not
 * verify is logged (message of the failure only, never the token or the nonce) and the login fails.
 */
abstract class MicrosoftAuthenticator extends Authenticator
{
    /**
     * @param string $logDirectory The log directory of the project (`Core::$logDirectory`, with trailing slash)
     * @param string $cacheDirectory A writable directory for the cache of the signing keys of Microsoft
     */
    protected function __construct(
        HttpRequest $httpRequest,
        AuthSession $authSession,
        int $maxAllowedWrongPasswordAttempts,
        private readonly AbstractSessionHandler $sessionHandler,
        private readonly string $logDirectory,
        private readonly string $cacheDirectory,
    ) {
        parent::__construct(
            httpRequest: $httpRequest,
            authSession: $authSession,
            maxAllowedWrongPasswordAttempts: $maxAllowedWrongPasswordAttempts,
        );
    }

    /**
     * @param string $ssoNonce A random value (for example `bin2hex(random_bytes(16))`) that the project keeps in the
     *     session until the token comes back
     *
     * @throws LogicException if the user is already logged in
     * @throws InvalidArgumentException if the tenant ID is not a GUID or a domain name
     */
    protected function redirectToMicrosoftLogin(
        string $tenantId,
        string $clientId,
        string $redirectUri,
        #[SensitiveParameter]
        string $ssoNonce,
    ): void {
        if ($this->authSession->isLoggedIn()) {
            throw new LogicException(message: 'User is already logged in');
        }
        $this->sessionHandler->changeCookieSameSiteToNone();
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: MicrosoftLoginUri::create(
                tenantId: $tenantId,
                clientId: $clientId,
                redirectUri: $redirectUri,
                ssoNonce: $ssoNonce,
            ),
            httpRequest: $this->httpRequest,
        );
    }

    protected function microsoftIdTokenLogin(
        string $tenantId,
        string $clientId,
        #[SensitiveParameter]
        string $ssoNonce,
        #[SensitiveParameter]
        string $microsoftIdToken,
    ): bool {
        try {
            $authWebToken = new MicrosoftIdToken(
                tenantId: $tenantId,
                clientId: $clientId,
                ssoNonce: $ssoNonce,
                jwtString: $microsoftIdToken,
                cacheDirectory: $this->cacheDirectory,
            );
            // A token without email address is not usable for the login
            $authWebToken->getUserName();
        } catch (UnauthorizedException|InvalidArgumentException|RuntimeException $exception) {
            $this->logFailure(exception: $exception);
            $this->authResult = AuthResultEnum::FAILED_SSO_LOGIN;

            return false;
        }

        return $this->authWebTokenLogin(authMethod: AuthMethodEnum::MICROSOFT, authWebToken: $authWebToken);
    }

    private function logFailure(Throwable $exception): void
    {
        new LogFile(logDirectory: $this->logDirectory, group: 'ssoMicrosoft', logFileName: 'ssoMicrosoft')->write(
            line: $exception::class . ': ' . $exception->getMessage(),
        );
    }
}
