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
use actra\yuf\core\NativeResponseSender;
use actra\yuf\core\ResponseSender;
use actra\yuf\exception\UnauthorizedException;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use SensitiveParameter;
use Throwable;

/**
 * Extension point: the login with a Microsoft account (OpenID Connect). A project class extends it, redirects the user
 * with `redirectToMicrosoftLogin()` and calls `microsoftIdTokenLogin()` in the callback that receives the posted ID
 * token, and finds the user by the account (`findAuthUserByMicrosoftAccount()`).
 *
 * The user is identified by the object ID and the tenant ID of the token (claims `oid` and `tid`), never by the email
 * address, which the user or a tenant administrator can change. yuf creates the nonce of the request, keeps it in its
 * own short-lived cookie (`SsoNonceCookie`) and deletes it when the token comes back: the project stores no nonce, and
 * the session cookie keeps its `SameSite` setting. A token that does not verify is logged (message of the failure
 * only, never the token or the nonce) and the login fails.
 */
abstract class MicrosoftAuthenticator extends Authenticator
{
    private readonly SsoNonceCookie $nonceCookie;

    /**
     * @param string $logDirectory The log directory of the project (`Core::$logDirectory`, with trailing slash)
     * @param string $cacheDirectory A writable directory for the cache of the signing keys of Microsoft
     */
    protected function __construct(
        HttpRequest $httpRequest,
        AuthSession $authSession,
        int $maxAllowedWrongPasswordAttempts,
        private readonly string $logDirectory,
        private readonly string $cacheDirectory,
        SsoNonceCookieWriter $nonceCookieWriter = new NativeSsoNonceCookieWriter(),
    ) {
        parent::__construct(
            httpRequest: $httpRequest,
            authSession: $authSession,
            maxAllowedWrongPasswordAttempts: $maxAllowedWrongPasswordAttempts,
        );
        $this->nonceCookie = new SsoNonceCookie(httpRequest: $httpRequest, cookieWriter: $nonceCookieWriter);
    }

    /**
     * Sends a cookie with a new nonce and redirects the user to Microsoft.
     *
     * @param string $redirectUri The callback URI registered at Microsoft (the page that calls
     *     `microsoftIdTokenLogin()`): the nonce cookie is limited to its path
     *
     * @throws LogicException if the user is already logged in
     * @throws InvalidArgumentException if the tenant ID is not a GUID or a domain name, or the redirect URI has no
     *     absolute path
     */
    protected function redirectToMicrosoftLogin(
        string $tenantId,
        string $clientId,
        string $redirectUri,
        ResponseSender $responseSender = new NativeResponseSender(),
    ): never {
        if ($this->authSession->isLoggedIn()) {
            throw new LogicException(message: 'User is already logged in');
        }
        MicrosoftTenantId::assertValid(tenantId: $tenantId);
        HttpResponse::redirectAndExit(
            relativeOrAbsoluteUri: MicrosoftLoginUri::create(
                tenantId: $tenantId,
                clientId: $clientId,
                redirectUri: $redirectUri,
                ssoNonce: $this->nonceCookie->issue(redirectUri: $redirectUri),
            ),
            httpRequest: $this->httpRequest,
            responseSender: $responseSender,
        );
    }

    /**
     * Verifies the posted ID token against the nonce cookie of the request (deleted in every case, so a nonce is used
     * once), finds the user of the account and logs in. A token without the nonce cookie of this browser fails.
     *
     * @param string $redirectUri The URI of `redirectToMicrosoftLogin()`
     *
     * @throws LogicException if this authenticator rejected a user or logged one in already, or the user is already
     *     logged in
     */
    protected function microsoftIdTokenLogin(
        string $tenantId,
        string $clientId,
        string $redirectUri,
        #[SensitiveParameter]
        string $microsoftIdToken,
    ): bool {
        $this->assertNoLoginTried();
        try {
            $idToken = new MicrosoftIdToken(
                tenantId: $tenantId,
                clientId: $clientId,
                ssoNonce: $this->nonceCookie->consume(redirectUri: $redirectUri),
                jwtString: $microsoftIdToken,
                cacheDirectory: $this->cacheDirectory,
            );
        } catch (UnauthorizedException|InvalidArgumentException|RuntimeException $exception) {
            $this->logFailure(exception: $exception);
            $this->authResult = AuthResultEnum::FAILED_SSO_LOGIN;

            return false;
        }
        $objectId = $idToken->getObjectId();

        return $this->logInIdentifiedUser(
            authMethod: AuthMethodEnum::MICROSOFT,
            authUser: $this->findAuthUserByMicrosoftAccount(objectId: $objectId, tenantId: $idToken->getTenantId()),
            identifier: $objectId,
        );
    }

    /**
     * Finds the user of a Microsoft account by the object ID and tenant ID the project stored for the user (both GUIDs
     * in lower case, verified by the token). Never look the user up by the email address.
     *
     * @return ?AuthUser `null` if no user is linked to the account
     */
    abstract protected function findAuthUserByMicrosoftAccount(string $objectId, string $tenantId): ?AuthUser;

    private function logFailure(Throwable $exception): void
    {
        new LogFile(logDirectory: $this->logDirectory, group: 'ssoMicrosoft', logFileName: 'ssoMicrosoft')->write(
            line: $exception::class . ': ' . $exception->getMessage(),
        );
    }
}
