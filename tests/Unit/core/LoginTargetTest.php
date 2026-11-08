<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\auth\LoginHandOver;
use actra\yuf\auth\SharedLogin;
use actra\yuf\auth\SharedLoginSite;
use actra\yuf\clock\FixedClock;
use actra\yuf\core\LoginTarget;
use actra\yuf\tests\Double\auth\RecordingSsoNonceCookieWriter;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use DateTimeImmutable;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LoginTargetTest extends TestCase
{
    private TemplateWorkDirectory $workDirectory;
    private RecordingSsoNonceCookieWriter $cookieWriter;
    private LoginHandOver $loginHandOver;

    #[Override]
    protected function setUp(): void
    {
        $this->workDirectory = new TemplateWorkDirectory();
        $this->cookieWriter = new RecordingSsoNonceCookieWriter();
        $this->loginHandOver = new LoginHandOver(
            directory: $this->workDirectory->cacheDirectory . 'hand-over',
            clock: new FixedClock(now: new DateTimeImmutable()),
            cookieWriter: $this->cookieWriter,
            purgeProbabilityPercent: 0,
        );
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    private function createRemoteTarget(string $url = 'https://login.example.net/login.html'): LoginTarget
    {
        return LoginTarget::remote(url: $url, loginHandOver: $this->loginHandOver, handOverPath: '/login-ticket.html');
    }

    public function testLocalTarget(): void
    {
        $loginTarget = LoginTarget::local(path: '/de/login.html?lang=de');

        $this->assertSame('/de/login.html?lang=de', $loginTarget->uri);
        $this->assertNull($loginTarget->host);
        $this->assertSame('/de/login.html', $loginTarget->path);
        $this->assertFalse($loginTarget->isRemote());
    }

    public function testRemoteTarget(): void
    {
        $loginTarget = $this->createRemoteTarget(url: 'https://Login.Example.net/login.html?lang=de');

        $this->assertSame('https://Login.Example.net/login.html?lang=de', $loginTarget->uri);
        $this->assertSame('login.example.net', $loginTarget->host);
        $this->assertSame('/login.html', $loginTarget->path);
        $this->assertTrue($loginTarget->isRemote());
    }

    #[DataProvider('invalidLocalPaths')]
    public function testInvalidLocalPathThrows(string $path): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs('The login path must be a local path starting with "/"');

        LoginTarget::local(path: $path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLocalPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['login/'];
        yield 'absolute URL' => ['https://example.com/login/'];
        yield 'protocol relative' => ['//example.com/login/'];
    }

    #[DataProvider('invalidRemoteUrls')]
    public function testInvalidRemoteUrlThrows(string $url): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->createRemoteTarget(url: $url);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRemoteUrls(): iterable
    {
        yield 'http' => ['http://login.example.net/login.html'];
        yield 'uppercase scheme' => ['HTTPS://login.example.net/login.html'];
        yield 'no host' => ['https:///login.html'];
        yield 'no path' => ['https://login.example.net'];
        yield 'local path' => ['/login.html'];
        yield 'protocol relative' => ['//login.example.net/login.html'];
        yield 'double slash path' => ['https://login.example.net//example.net/'];
        yield 'user info' => ['https://user@login.example.net/login.html'];
        yield 'user and password' => ['https://user:pw@login.example.net/login.html'];
        yield 'port' => ['https://login.example.net:8443/login.html'];
        yield 'line feed' => ["https://login.example.net/login.html\nX: y"];
        yield 'nul' => ["https://login.example.net/a\0b"];
        yield 'space' => ['https://login.example.net/a b'];
        yield 'backslash' => ['https://login.example.net\\example.net/'];
        yield 'IDN' => ['https://lögin.example.net/login.html'];
        yield 'trailing dot' => ['https://login.example.net./login.html'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidHandOverPaths(): iterable
    {
        yield 'empty' => [''];
        yield 'relative' => ['login-ticket.html'];
        yield 'absolute URL' => ['https://example.net/login-ticket.html'];
        yield 'protocol relative' => ['//example.net/'];
        yield 'with query' => ['/login-ticket.html?a=1'];
    }

    #[DataProvider('invalidHandOverPaths')]
    public function testInvalidHandOverPathThrows(string $handOverPath): void
    {
        $this->expectException(InvalidArgumentException::class);

        LoginTarget::remote(
            url: 'https://login.example.net/login.html',
            loginHandOver: $this->loginHandOver,
            handOverPath: $handOverPath,
        );
    }

    public function testLocalLoginUriHasTheRequestUriAsReturnTarget(): void
    {
        $httpRequest = HttpRequestFactory::create(uri: '/a/?b=1');

        $this->assertSame(
            '/login/?returnTo=%2Fa%2F%3Fb%3D1',
            LoginTarget::local(path: '/login/')->createLoginUri(httpRequest: $httpRequest),
        );
        $this->assertSame([], $this->cookieWriter->setCookies);
    }

    public function testRemoteLoginUriHasOnlyTheIdOfANewLoginRequest(): void
    {
        $httpRequest = HttpRequestFactory::create(host: 'site-a.example.com', uri: '/de/page.html?a=1');

        $loginUri = $this->createRemoteTarget(url: 'https://login.example.net/login.html?lang=de')->createLoginUri(
            httpRequest: $httpRequest,
        );

        $this->assertMatchesRegularExpression(
            '#^https://login\.example\.net/login\.html\?lang=de&loginRequest=[0-9a-f]{64}$#D',
            $loginUri,
        );
        $this->assertCount(1, $this->cookieWriter->setCookies);
        $this->assertSame('__Host-yuf-login-browser', $this->cookieWriter->setCookies[0]['name']);
    }

    public function testRemoteLoginUriOfAnInvalidRequestUriReturnsToTheRoot(): void
    {
        $httpRequest = HttpRequestFactory::create(host: 'site-a.example.com', uri: '//example.net/');

        $this->assertStringStartsWith(
            'https://login.example.net/login.html?loginRequest=',
            $this->createRemoteTarget()->createLoginUri(httpRequest: $httpRequest),
        );
    }

    public function testLocalLoginRequestIsRecognizedByPath(): void
    {
        $loginTarget = LoginTarget::local(path: '/login.html?lang=de');

        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(uri: '/login.html?x=1'),
        ));
        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'other.example.com', uri: '/login.html'),
        ));
        $this->assertFalse($loginTarget->isLoginRequest(httpRequest: HttpRequestFactory::create(uri: '/other.html')));
    }

    public function testRemoteLoginRequestIsRecognizedByHostAndPath(): void
    {
        $loginTarget = $this->createRemoteTarget();

        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'login.example.net', uri: '/login.html?loginRequest=x'),
        ));
        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'LOGIN.example.net', uri: '/login.html'),
        ));
        $this->assertFalse($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'site-a.example.com', uri: '/login.html'),
        ));
        $this->assertFalse($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'login.example.net', uri: '/other.html'),
        ));
    }

    public function testSharedTargetIsALocalLoginPage(): void
    {
        $loginTarget = $this->createSharedTarget();

        $this->assertSame('/fr/login.html?a=1', $loginTarget->uri);
        $this->assertSame('/fr/login.html', $loginTarget->path);
        $this->assertFalse($loginTarget->isRemote());
        $this->assertTrue($loginTarget->isLoginRequest(
            httpRequest: HttpRequestFactory::create(host: 'site-a.example.com', uri: '/fr/login.html'),
        ));
    }

    public function testSharedLoginUriChecksAtTheLoginHostFirst(): void
    {
        $loginUri = $this->createSharedTarget()->createLoginUri(
            httpRequest: HttpRequestFactory::create(host: 'site-a.example.com', uri: '/fr/account.html'),
        );

        $this->assertMatchesRegularExpression(
            '#^https://login\.example\.net/login-hand-over\.html\?loginCheck=[0-9a-f]{64}$#D',
            $loginUri,
        );
    }

    public function testSharedLoginUriChecksAgainAfterAnEarlierCheck(): void
    {
        // A login on another host since the last check is found: no state of an earlier check skips the check
        $loginUri = $this->createSharedTarget()->createLoginUri(
            httpRequest: HttpRequestFactory::create(
                host: 'site-a.example.com',
                uri: '/fr/account.html',
                cookies: ['__Host-yuf-login-checked' => '1'],
            ),
        );

        $this->assertStringStartsWith('https://login.example.net/login-hand-over.html?loginCheck=', $loginUri);
    }

    public function testSharedLoginUriOfTheReturnOfACheckIsTheLocalLoginPage(): void
    {
        $loginUri = $this->createSharedTarget()->createLoginUri(
            httpRequest: HttpRequestFactory::create(
                host: 'site-a.example.com',
                uri: '/fr/account.html?loginChecked=1',
                queryParameters: [SharedLogin::CHECKED_PARAMETER => '1'],
            ),
        );

        $this->assertSame('/fr/login.html?a=1&returnTo=%2Ffr%2Faccount.html%3FloginChecked%3D1', $loginUri);
        $this->assertSame([], $this->cookieWriter->setCookies);
    }

    private function createSharedTarget(): LoginTarget
    {
        return LoginTarget::shared(
            sharedLogin: new SharedLogin(
                directory: $this->workDirectory->cacheDirectory . 'hand-over',
                loginLifetimeInSeconds: 3600,
                clock: new FixedClock(now: new DateTimeImmutable()),
                cookieWriter: $this->cookieWriter,
                purgeProbabilityPercent: 0,
            ),
            site: new SharedLoginSite(
                loginPath: '/fr/login.html?a=1',
                handOverPath: '/login-hand-over.html',
                loginHostUrl: 'https://login.example.net/login-hand-over.html',
            ),
        );
    }
}
