<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\auth\TestAuthUser;
use actra\yuf\tests\Double\auth\TestJwtIssuer;
use actra\yuf\tests\Double\auth\TestMicrosoftAuthenticator;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\session\NonStartingSessionHandler;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The login with a Microsoft ID token (the signing key comes from the cache directory, no network). The token is
 * issued for the current time of the test process, so the default clock of the token applies.
 */
final class MicrosoftAuthenticatorTest extends TestCase
{
    private const string TENANT_ID = '11111111-2222-3333-4444-555555555555';
    private const string CLIENT_ID = 'client-id';
    private const string NONCE = 'secret-nonce-1';

    private string $directory;
    private TestJwtIssuer $issuer;
    private AuthSession $authSession;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'yuf-sso-auth-test-'
            . bin2hex(string: random_bytes(length: 8)) . DIRECTORY_SEPARATOR;
        mkdir(directory: $this->directory . 'cache', recursive: true);
        mkdir(directory: $this->directory . 'log');
        $this->issuer = new TestJwtIssuer();
        file_put_contents(
            filename: $this->directory . 'cache' . DIRECTORY_SEPARATOR . 'ssoMicrosoftKeys-'
            . MicrosoftAuthenticatorTest::TENANT_ID . '.json',
            data: $this->issuer->createKeySetJson(),
        );
        $this->authSession = new AuthSession(session: new Session(storage: new ArraySessionStorage()));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->removeDirectory(path: rtrim(string: $this->directory, characters: DIRECTORY_SEPARATOR));
    }

    private function removeDirectory(string $path): void
    {
        $entries = scandir(directory: $path);
        foreach ($entries === false ? [] : $entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            is_dir(filename: $path . DIRECTORY_SEPARATOR . $entry)
                ? $this->removeDirectory(path: $path . DIRECTORY_SEPARATOR . $entry)
                : unlink(filename: $path . DIRECTORY_SEPARATOR . $entry);
        }
        rmdir(directory: $path);
    }

    private function createAuthenticator(): TestMicrosoftAuthenticator
    {
        return new TestMicrosoftAuthenticator(
            httpRequest: HttpRequestFactory::create(),
            authSession: $this->authSession,
            sessionHandler: new NonStartingSessionHandler(),
            logDirectory: $this->directory . 'log' . DIRECTORY_SEPARATOR,
            cacheDirectory: $this->directory . 'cache',
            authUser: TestAuthUser::create(accessRights: []),
        );
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function createIdToken(array $changes = []): string
    {
        $now = time();

        return $this->issuer->createJwt(payload: $changes + [
            'nbf' => $now - 10,
            'iat' => $now - 10,
            'exp' => $now + 3600,
            'aud' => MicrosoftAuthenticatorTest::CLIENT_ID,
            'tid' => MicrosoftAuthenticatorTest::TENANT_ID,
            'iss' => 'https://login.microsoftonline.com/' . MicrosoftAuthenticatorTest::TENANT_ID . '/v2.0',
            'nonce' => MicrosoftAuthenticatorTest::NONCE,
            'email' => 'user@example.org',
        ]);
    }

    public function testValidTokenLogsTheUserIn(): void
    {
        $authenticator = $this->createAuthenticator();

        $loggedIn = $authenticator->login(
            tenantId: MicrosoftAuthenticatorTest::TENANT_ID,
            clientId: MicrosoftAuthenticatorTest::CLIENT_ID,
            nonce: MicrosoftAuthenticatorTest::NONCE,
            idToken: $this->createIdToken(),
        );

        $this->assertTrue($loggedIn);
        $this->assertSame(AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN, $authenticator->authResult);
        $this->assertTrue($this->authSession->isLoggedIn());
    }

    public function testUnknownEmailAddressFailsTheLogin(): void
    {
        $authenticator = $this->createAuthenticator();

        $loggedIn = $authenticator->login(
            tenantId: MicrosoftAuthenticatorTest::TENANT_ID,
            clientId: MicrosoftAuthenticatorTest::CLIENT_ID,
            nonce: MicrosoftAuthenticatorTest::NONCE,
            idToken: $this->createIdToken(changes: ['email' => 'nobody@example.org']),
        );

        $this->assertFalse($loggedIn);
        $this->assertSame(AuthResultEnum::ERROR_UNKNOWN_USER_NAME, $authenticator->authResult);
    }

    public function testTokenThatDoesNotVerifyFailsTheLoginAndIsLoggedWithoutTokenAndNonce(): void
    {
        $authenticator = $this->createAuthenticator();
        $idToken = $this->createIdToken(changes: ['aud' => 'other-client']);

        $loggedIn = $authenticator->login(
            tenantId: MicrosoftAuthenticatorTest::TENANT_ID,
            clientId: MicrosoftAuthenticatorTest::CLIENT_ID,
            nonce: MicrosoftAuthenticatorTest::NONCE,
            idToken: $idToken,
        );

        $this->assertFalse($loggedIn);
        $this->assertSame(AuthResultEnum::FAILED_SSO_LOGIN, $authenticator->authResult);
        $this->assertFalse($this->authSession->isLoggedIn());
        $log = $this->readLog();
        $this->assertStringContainsString('UnauthorizedException: Missing or invalid aud', $log);
        $this->assertStringNotContainsString($idToken, $log);
        $this->assertStringNotContainsString(MicrosoftAuthenticatorTest::NONCE, $log);
        $this->assertStringNotContainsString('user@example.org', $log);
    }

    public function testInvalidTenantIdFailsTheLogin(): void
    {
        $authenticator = $this->createAuthenticator();

        $loggedIn = $authenticator->login(
            tenantId: '../etc',
            clientId: MicrosoftAuthenticatorTest::CLIENT_ID,
            nonce: MicrosoftAuthenticatorTest::NONCE,
            idToken: $this->createIdToken(),
        );

        $this->assertFalse($loggedIn);
        $this->assertSame(AuthResultEnum::FAILED_SSO_LOGIN, $authenticator->authResult);
    }

    private function readLog(): string
    {
        $content = '';
        $paths = glob(pattern: $this->directory . 'log/ssoMicrosoft/*/*/*/*.log');
        foreach ($paths === false ? [] : $paths as $path) {
            $content .= file_get_contents(filename: $path);
        }

        return $content;
    }
}
