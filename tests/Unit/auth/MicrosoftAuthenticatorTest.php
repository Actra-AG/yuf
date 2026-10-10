<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\AuthResultEnum;
use actra\yuf\auth\AuthSession;
use actra\yuf\auth\SsoNonceCookie;
use actra\yuf\core\ResponseSender;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\auth\RecordingSsoNonceCookieWriter;
use actra\yuf\tests\Double\auth\TestAuthUser;
use actra\yuf\tests\Double\auth\TestJwtIssuer;
use actra\yuf\tests\Double\auth\TestMicrosoftAuthenticator;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\core\RecordingResponseSender;
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
    private const string NONCE = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';
    private const string OBJECT_ID = '99999999-8888-7777-6666-555555555555';
    private const string REDIRECT_URI = 'https://example.com/sso/callback';

    private string $directory;
    private TestJwtIssuer $issuer;
    private AuthSession $authSession;
    private RecordingSsoNonceCookieWriter $cookieWriter;

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
        $this->cookieWriter = new RecordingSsoNonceCookieWriter();
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

    /**
     * @param ?string $nonceCookie The nonce cookie of the request, `null` for none
     */
    private function createAuthenticator(
        ?string $nonceCookie = MicrosoftAuthenticatorTest::NONCE,
    ): TestMicrosoftAuthenticator {
        return new TestMicrosoftAuthenticator(
            httpRequest: HttpRequestFactory::create(
                cookies: $nonceCookie === null ? [] : [SsoNonceCookie::NAME => $nonceCookie],
            ),
            authSession: $this->authSession,
            logDirectory: $this->directory . 'log' . DIRECTORY_SEPARATOR,
            cacheDirectory: $this->directory . 'cache',
            authUser: TestAuthUser::create(accessRights: []),
            nonceCookieWriter: $this->cookieWriter,
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
            'oid' => MicrosoftAuthenticatorTest::OBJECT_ID,
            'email' => 'user@example.org',
        ]);
    }

    private function login(TestMicrosoftAuthenticator $authenticator, string $idToken): bool
    {
        return $authenticator->login(
            tenantId: MicrosoftAuthenticatorTest::TENANT_ID,
            clientId: MicrosoftAuthenticatorTest::CLIENT_ID,
            redirectUri: MicrosoftAuthenticatorTest::REDIRECT_URI,
            idToken: $idToken,
        );
    }

    public function testValidTokenLogsTheUserInByObjectIdAndTenantId(): void
    {
        $authenticator = $this->createAuthenticator();

        $loggedIn = $this->login(authenticator: $authenticator, idToken: $this->createIdToken());

        $this->assertTrue($loggedIn);
        $this->assertSame(AuthResultEnum::SUCCESSFUL_MICROSOFT_LOGIN, $authenticator->authResult);
        $this->assertTrue($this->authSession->isLoggedIn());
        $this->assertSame(
            [[MicrosoftAuthenticatorTest::OBJECT_ID, MicrosoftAuthenticatorTest::TENANT_ID]],
            $authenticator->lookups,
        );
    }

    public function testEmailClaimDoesNotIdentifyTheUser(): void
    {
        $authenticator = $this->createAuthenticator();
        // The mail address of a known user, another account
        $idToken = $this->createIdToken(
            changes: ['oid' => '00000000-0000-0000-0000-000000000001', 'email' => 'user@example.org'],
        );

        $loggedIn = $this->login(authenticator: $authenticator, idToken: $idToken);

        $this->assertFalse($loggedIn);
        $this->assertSame(AuthResultEnum::ERROR_UNKNOWN_USER_NAME, $authenticator->authResult);
        $this->assertSame(
            [['00000000-0000-0000-0000-000000000001', MicrosoftAuthenticatorTest::TENANT_ID]],
            $authenticator->lookups,
        );
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testUnknownAccountIsLoggedLikeAnUnknownUser(): void
    {
        $authenticator = $this->createAuthenticator();

        $loggedIn = $this->login(
            authenticator: $authenticator,
            idToken: $this->createIdToken(changes: ['oid' => '00000000-0000-0000-0000-000000000001']),
        );

        $this->assertFalse($loggedIn);
        $this->assertSame([AuthResultEnum::ERROR_UNKNOWN_USER_NAME], $authenticator->loggedResults);
    }

    public function testNonceCookieIsDeletedOnSuccessOnTheCallbackPath(): void
    {
        $this->login(authenticator: $this->createAuthenticator(), idToken: $this->createIdToken());

        $this->assertSame(
            [['name' => SsoNonceCookie::NAME, 'path' => '/sso/callback']],
            $this->cookieWriter->deletedCookies,
        );
    }

    public function testNonceCookieIsDeletedWhenTheTokenDoesNotVerify(): void
    {
        $authenticator = $this->createAuthenticator();

        $loggedIn = $this->login(
            authenticator: $authenticator,
            idToken: $this->createIdToken(changes: ['aud' => 'other-client']),
        );

        $this->assertFalse($loggedIn);
        $this->assertCount(1, $this->cookieWriter->deletedCookies);
    }

    public function testTokenWithoutNonceCookieFailsTheLogin(): void
    {
        $authenticator = $this->createAuthenticator(nonceCookie: null);

        $loggedIn = $this->login(authenticator: $authenticator, idToken: $this->createIdToken());

        $this->assertFalse($loggedIn);
        $this->assertSame(AuthResultEnum::FAILED_SSO_LOGIN, $authenticator->authResult);
        $this->assertSame([], $authenticator->lookups);
        $this->assertStringContainsString('Missing SSO nonce cookie', $this->readLog());
    }

    public function testTokenWithAnEmptyNonceAndNoCookieFailsTheLogin(): void
    {
        $authenticator = $this->createAuthenticator(nonceCookie: null);

        $idToken = $this->createIdToken(changes: ['nonce' => '']);

        $loggedIn = $this->login(authenticator: $authenticator, idToken: $idToken);

        $this->assertFalse($loggedIn);
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testTokenWithAnEmptyNonceAndAnEmptyCookieFailsTheLogin(): void
    {
        $authenticator = $this->createAuthenticator(nonceCookie: '');

        $idToken = $this->createIdToken(changes: ['nonce' => '']);

        $loggedIn = $this->login(authenticator: $authenticator, idToken: $idToken);

        $this->assertFalse($loggedIn);
        $this->assertFalse($this->authSession->isLoggedIn());
    }

    public function testTokenWithAnotherNonceThanTheCookieFailsTheLogin(): void
    {
        $authenticator = $this->createAuthenticator();
        $idToken = $this->createIdToken(changes: ['nonce' => str_repeat(string: 'f', times: 64)]);

        $loggedIn = $this->login(authenticator: $authenticator, idToken: $idToken);

        $this->assertFalse($loggedIn);
        $this->assertSame(AuthResultEnum::FAILED_SSO_LOGIN, $authenticator->authResult);
    }

    public function testTokenThatDoesNotVerifyFailsTheLoginAndIsLoggedWithoutTokenAndNonce(): void
    {
        $authenticator = $this->createAuthenticator();
        $idToken = $this->createIdToken(changes: ['aud' => 'other-client']);

        $loggedIn = $this->login(authenticator: $authenticator, idToken: $idToken);

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
            redirectUri: MicrosoftAuthenticatorTest::REDIRECT_URI,
            idToken: $this->createIdToken(),
        );

        $this->assertFalse($loggedIn);
        $this->assertSame(AuthResultEnum::FAILED_SSO_LOGIN, $authenticator->authResult);
    }

    public function testRedirectSendsTheNonceOnlyInTheCookieOfTheCallbackPathAndInTheRequest(): void
    {
        $authenticator = $this->createAuthenticator();

        $response = RecordingResponseSender::capture(
            action: fn(ResponseSender $sender) => $authenticator->redirect(
                tenantId: MicrosoftAuthenticatorTest::TENANT_ID,
                clientId: MicrosoftAuthenticatorTest::CLIENT_ID,
                redirectUri: MicrosoftAuthenticatorTest::REDIRECT_URI,
                responseSender: $sender,
            ),
        );

        $this->assertCount(1, $this->cookieWriter->setCookies);
        $cookie = $this->cookieWriter->setCookies[0];
        $this->assertSame(SsoNonceCookie::NAME, $cookie['name']);
        $this->assertSame('/sso/callback', $cookie['path']);
        $this->assertSame(600, $cookie['maxAge']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $cookie['value']);
        $location = $response->getHeader(key: 'Location');
        $this->assertNotNull($location);
        $this->assertStringContainsString('nonce=' . $cookie['value'], $location);
    }

    public function testRedirectCreatesANewNonceEveryTime(): void
    {
        for ($request = 0; $request < 2; $request++) {
            RecordingResponseSender::capture(
                action: fn(ResponseSender $sender) => $this->createAuthenticator()->redirect(
                    tenantId: MicrosoftAuthenticatorTest::TENANT_ID,
                    clientId: MicrosoftAuthenticatorTest::CLIENT_ID,
                    redirectUri: MicrosoftAuthenticatorTest::REDIRECT_URI,
                    responseSender: $sender,
                ),
            );
        }

        $values = array_column(array: $this->cookieWriter->setCookies, column_key: 'value');
        $this->assertCount(2, $values);
        $this->assertCount(2, array_unique(array: $values));
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
