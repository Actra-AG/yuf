<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\LoginHandOver;
use actra\yuf\auth\LoginHandOverTicket;
use actra\yuf\core\HttpRequest;
use actra\yuf\exception\UnauthorizedException;
use actra\yuf\tests\Double\auth\RecordingSsoNonceCookieWriter;
use actra\yuf\tests\Double\clock\AdjustableClock;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\template\TemplateWorkDirectory;
use DateTimeImmutable;
use InvalidArgumentException;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class LoginHandOverTest extends TestCase
{
    private const array ALLOWED_HOSTS = ['site-a.example.com', 'www.example.net'];
    private const string LOGIN_BROWSER = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string OTHER_BROWSER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string COOKIE = '__Host-yuf-login-browser';

    private TemplateWorkDirectory $workDirectory;
    private string $directory;
    private AdjustableClock $clock;
    private RecordingSsoNonceCookieWriter $cookieWriter;
    private LoginHandOver $loginHandOver;

    #[Override]
    protected function setUp(): void
    {
        $this->workDirectory = new TemplateWorkDirectory();
        $this->directory = $this->workDirectory->cacheDirectory . 'hand-over';
        $this->clock = new AdjustableClock(now: new DateTimeImmutable(datetime: '2026-11-08 12:00:00 UTC'));
        $this->cookieWriter = new RecordingSsoNonceCookieWriter();
        $this->loginHandOver = $this->createLoginHandOver();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    private function createLoginHandOver(): LoginHandOver
    {
        return new LoginHandOver(
            directory: $this->directory,
            clock: $this->clock,
            cookieWriter: $this->cookieWriter,
            purgeProbabilityPercent: 0,
        );
    }

    /**
     * @param array<string, string> $cookies
     * @param array<string, string> $queryParameters
     */
    private static function request(
        string $host = 'site-a.example.com',
        string $uri = '/de/page.html?a=1',
        array $cookies = [],
        array $queryParameters = [],
    ): HttpRequest {
        return HttpRequestFactory::create(
            host: $host,
            uri: $uri,
            cookies: $cookies,
            queryParameters: $queryParameters,
        );
    }

    /**
     * Starts a login on the site and returns the request ID and the browser secret of the site.
     *
     * @return array{string, string}
     */
    private function startLogin(string $uri = '/de/page.html?a=1'): array
    {
        $requestId = $this->loginHandOver->startLogin(
            httpRequest: LoginHandOverTest::request(uri: $uri),
            handOverPath: '/login-ticket.html',
        );

        return [$requestId, $this->lastCookieValue()];
    }

    /**
     * @return list<string>
     */
    private function listFiles(): array
    {
        $files = glob(pattern: $this->directory . '/*');

        return $files === false ? [] : $files;
    }

    private function lastCookieValue(): string
    {
        $cookie = array_last(array: $this->cookieWriter->setCookies);
        $this->assertNotNull($cookie);

        return $cookie['value'];
    }

    private function loginHostRequest(string $browser = LoginHandOverTest::LOGIN_BROWSER): HttpRequest
    {
        return LoginHandOverTest::request(
            host: 'login.example.net',
            uri: '/login.html',
            cookies: [LoginHandOverTest::COOKIE => $browser],
        );
    }

    /**
     * Runs the login host part and returns the ticket ID.
     */
    private function logInOnLoginHost(string $requestId, string $subject = '42'): string
    {
        $this->assertNotNull($this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        ));
        $url = $this->loginHandOver->createHandOverUrl(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            subject: $subject,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );
        $this->assertSame(
            1,
            preg_match(
                pattern: '#^https://site-a\.example\.com/login-ticket\.html\?loginTicket=([0-9a-f]{64})$#D',
                subject: $url,
                matches: $matches,
            ),
            $url,
        );

        return $matches[1];
    }

    private function redeemRequest(string $ticketId, ?string $browser, string $host = 'site-a.example.com'): HttpRequest
    {
        return LoginHandOverTest::request(
            host: $host,
            uri: '/login-ticket.html?loginTicket=' . $ticketId,
            cookies: $browser === null ? [] : [LoginHandOverTest::COOKIE => $browser],
            queryParameters: ['loginTicket' => $ticketId],
        );
    }

    private function redeem(
        string $ticketId,
        ?string $browser,
        string $host = 'site-a.example.com',
    ): LoginHandOverTicket {
        return $this->loginHandOver->redeemTicket(
            httpRequest: $this->redeemRequest(ticketId: $ticketId, browser: $browser, host: $host),
        );
    }

    public function testLoginIsHandedOverToTheSite(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();
        $ticketId = $this->logInOnLoginHost(requestId: $requestId, subject: 'user-7');

        $ticket = $this->redeem(ticketId: $ticketId, browser: $siteBrowser);

        $this->assertSame('user-7', $ticket->subject);
        $this->assertSame('/de/page.html?a=1', $ticket->returnPath);
    }

    public function testFilesKeepNoIdsAndOnlyTheOwnerCanReadThem(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();

        $files = $this->listFiles();
        $this->assertCount(1, $files);
        $file = $files[0];
        $this->assertStringNotContainsString($requestId, $file);
        $content = (string) file_get_contents(filename: $file);
        $this->assertStringNotContainsString($requestId, $content);
        $this->assertStringNotContainsString($siteBrowser, $content);
        $this->assertSame(0o600, fileperms(filename: $file) & 0o777);
        $this->assertSame(0o700, fileperms(filename: $this->directory) & 0o777);
    }

    public function testBrowserCookieIsSentForTheWholeHost(): void
    {
        $this->startLogin();

        $cookie = $this->cookieWriter->setCookies[0] ?? null;
        $this->assertNotNull($cookie);
        $this->assertSame([LoginHandOverTest::COOKIE, 900, '/'], [$cookie['name'], $cookie['maxAge'], $cookie['path']]);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $cookie['value']);
    }

    public function testValidBrowserCookieIsKept(): void
    {
        $this->loginHandOver->startLogin(
            httpRequest: LoginHandOverTest::request(
                cookies: [LoginHandOverTest::COOKIE => LoginHandOverTest::OTHER_BROWSER],
            ),
            handOverPath: '/login-ticket.html',
        );

        $this->assertSame(LoginHandOverTest::OTHER_BROWSER, $this->lastCookieValue());
    }

    public function testInvalidBrowserCookieIsReplaced(): void
    {
        $this->loginHandOver->startLogin(
            httpRequest: LoginHandOverTest::request(cookies: [LoginHandOverTest::COOKIE => 'chosen-by-attacker']),
            handOverPath: '/login-ticket.html',
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/D', $this->lastCookieValue());
    }

    public function testRequestUriThatIsNoLocalPathReturnsToTheRoot(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin(uri: '//example.net/');
        $ticketId = $this->logInOnLoginHost(requestId: $requestId);

        $ticket = $this->redeem(ticketId: $ticketId, browser: $siteBrowser);

        $this->assertSame('/', $ticket->returnPath);
    }

    public function testHostWithPortCannotStartALogin(): void
    {
        $this->expectException(LogicException::class);

        $this->loginHandOver->startLogin(
            httpRequest: LoginHandOverTest::request(host: 'site-a.example.com:8443'),
            handOverPath: '/login-ticket.html',
        );
    }

    public function testInvalidHandOverPathThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->loginHandOver->startLogin(httpRequest: LoginHandOverTest::request(), handOverPath: '//example.net/');
    }

    public function testFindLoginRequestHasTheHost(): void
    {
        [$requestId] = $this->startLogin();

        $loginRequest = $this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );

        $this->assertNotNull($loginRequest);
        $this->assertSame($requestId, $loginRequest->id);
        $this->assertSame('site-a.example.com', $loginRequest->host);
        $this->assertSame($this->clock->now()->getTimestamp() + 900, $loginRequest->expiresAt);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidRequestIds(): iterable
    {
        yield 'empty' => [''];
        yield 'short' => ['abc'];
        yield 'uppercase' => [str_repeat(string: 'A', times: 64)];
        yield 'path' => ['../' . str_repeat(string: 'a', times: 61)];
        yield 'unknown' => [str_repeat(string: 'c', times: 64)];
    }

    #[DataProvider('invalidRequestIds')]
    public function testInvalidOrUnknownRequestIsNotFound(string $requestId): void
    {
        $this->startLogin();

        $this->assertNull($this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        ));
    }

    public function testRequestOfAHostThatIsNotAllowedIsNotFound(): void
    {
        [$requestId] = $this->startLogin();

        $this->assertNull($this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: ['www.example.net'],
        ));
    }

    public function testExpiredRequestIsNotFound(): void
    {
        [$requestId] = $this->startLogin();
        $this->clock->advanceSeconds(seconds: 900);

        $this->assertNull($this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        ));
    }

    public function testRequestIsBoundToTheFirstBrowserOnTheLoginHost(): void
    {
        [$requestId] = $this->startLogin();
        $find = fn(string $browser): bool => $this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(browser: $browser),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        ) !== null;

        $this->assertTrue($find(LoginHandOverTest::LOGIN_BROWSER));
        $this->assertTrue($find(LoginHandOverTest::LOGIN_BROWSER));
        $this->assertFalse($find(LoginHandOverTest::OTHER_BROWSER));
    }

    public function testInvalidAllowedHostThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: str_repeat(string: 'c', times: 64),
            allowedHosts: ['https://site-a.example.com'],
        );
    }

    public function testHandOverUrlNeedsTheRequestToBeFoundFirst(): void
    {
        [$requestId] = $this->startLogin();

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs('Login request of another browser');

        $this->loginHandOver->createHandOverUrl(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            subject: '42',
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );
    }

    public function testHandOverUrlOfAnotherBrowserOnTheLoginHostIsRefused(): void
    {
        [$requestId] = $this->startLogin();
        $this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );

        $this->expectException(UnauthorizedException::class);

        $this->loginHandOver->createHandOverUrl(
            httpRequest: $this->loginHostRequest(browser: LoginHandOverTest::OTHER_BROWSER),
            requestId: $requestId,
            subject: '42',
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );
    }

    public function testRequestIsUsedOnce(): void
    {
        [$requestId] = $this->startLogin();
        $this->logInOnLoginHost(requestId: $requestId);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs('Unknown, expired or used login request');

        $this->loginHandOver->createHandOverUrl(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            subject: '42',
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );
    }

    public function testHandOverUrlForAHostThatIsNotAllowedIsRefused(): void
    {
        [$requestId] = $this->startLogin();
        $this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );

        $this->expectException(UnauthorizedException::class);

        $this->loginHandOver->createHandOverUrl(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            subject: '42',
            allowedHosts: ['www.example.net'],
        );
    }

    public function testHandOverUrlOfAnExpiredRequestIsRefused(): void
    {
        [$requestId] = $this->startLogin();
        $this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );
        $this->clock->advanceSeconds(seconds: 900);

        $this->expectException(UnauthorizedException::class);

        $this->loginHandOver->createHandOverUrl(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            subject: '42',
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );
    }

    public function testEmptySubjectThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->loginHandOver->createHandOverUrl(
            httpRequest: $this->loginHostRequest(),
            requestId: str_repeat(string: 'c', times: 64),
            subject: '',
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        );
    }

    public function testTicketIsUsedOnce(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();
        $ticketId = $this->logInOnLoginHost(requestId: $requestId);
        $this->redeem(ticketId: $ticketId, browser: $siteBrowser);

        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs('Unknown or used login ticket');

        $this->redeem(ticketId: $ticketId, browser: $siteBrowser);
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function otherBrowsers(): iterable
    {
        yield 'no cookie' => [null];
        yield 'other browser' => [LoginHandOverTest::OTHER_BROWSER];
        yield 'invalid cookie' => ['x'];
    }

    #[DataProvider('otherBrowsers')]
    public function testTicketOfAnotherBrowserIsRefusedAndUsedUp(?string $browser): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();
        $ticketId = $this->logInOnLoginHost(requestId: $requestId);

        try {
            $this->redeem(ticketId: $ticketId, browser: $browser);
            LoginHandOverTest::fail('The ticket of another browser was accepted.');
        } catch (UnauthorizedException $exception) {
            $this->assertSame('Login ticket of another browser', $exception->getMessage());
        }
        $this->expectException(UnauthorizedException::class);
        $this->expectExceptionMessageIs('Unknown or used login ticket');

        $this->redeem(ticketId: $ticketId, browser: $siteBrowser);
    }

    public function testTicketOfAnotherHostIsRefused(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();
        $ticketId = $this->logInOnLoginHost(requestId: $requestId);

        $this->expectException(UnauthorizedException::class);

        $this->redeem(ticketId: $ticketId, browser: $siteBrowser, host: 'www.example.net');
    }

    public function testExpiredTicketIsRefused(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();
        $ticketId = $this->logInOnLoginHost(requestId: $requestId);
        $this->clock->advanceSeconds(seconds: 60);

        $this->expectException(UnauthorizedException::class);

        $this->redeem(ticketId: $ticketId, browser: $siteBrowser);
    }

    public function testTicketIsValidForAlmostAMinute(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();
        $ticketId = $this->logInOnLoginHost(requestId: $requestId);
        $this->clock->advanceSeconds(seconds: 59);

        $ticket = $this->redeem(ticketId: $ticketId, browser: $siteBrowser);

        $this->assertSame('42', $ticket->subject);
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function invalidTicketParameters(): iterable
    {
        yield 'missing' => [[]];
        yield 'short' => [['loginTicket' => 'abc']];
        yield 'path' => [['loginTicket' => '../' . str_repeat(string: 'a', times: 61)]];
        yield 'unknown' => [['loginTicket' => str_repeat(string: 'd', times: 64)]];
    }

    /**
     * @param array<string, string> $queryParameters
     */
    #[DataProvider('invalidTicketParameters')]
    public function testInvalidOrUnknownTicketIsRefused(array $queryParameters): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->loginHandOver->redeemTicket(httpRequest: LoginHandOverTest::request(
            uri: '/login-ticket.html',
            cookies: [LoginHandOverTest::COOKIE => LoginHandOverTest::OTHER_BROWSER],
            queryParameters: $queryParameters,
        ));
    }

    public function testRequestIdIsNoTicket(): void
    {
        [$requestId, $siteBrowser] = $this->startLogin();

        $this->expectException(UnauthorizedException::class);

        $this->redeem(ticketId: $requestId, browser: $siteBrowser);
    }

    public function testDamagedRequestFileIsNotFound(): void
    {
        [$requestId] = $this->startLogin();
        file_put_contents(filename: $this->listFiles()[0] ?? '', data: '{"host": ["site-a.example.com"]}');

        $this->assertNull($this->loginHandOver->findLoginRequest(
            httpRequest: $this->loginHostRequest(),
            requestId: $requestId,
            allowedHosts: LoginHandOverTest::ALLOWED_HOSTS,
        ));
    }

    public function testExpiredFilesArePurged(): void
    {
        $this->startLogin();
        $this->clock->advanceSeconds(seconds: 800);
        $this->startLogin();
        $this->clock->advanceSeconds(seconds: 100);

        $this->assertSame(1, $this->loginHandOver->purgeExpired());
        $this->assertCount(1, $this->listFiles());
    }

    public function testDirectoryWritableByOthersIsRefused(): void
    {
        mkdir(directory: $this->directory, permissions: 0o700);
        chmod(filename: $this->directory, permissions: 0o777);

        $this->expectException(RuntimeException::class);

        $this->startLogin();
    }

    public function testPurgeWithoutDirectoryDeletesNothing(): void
    {
        $this->assertSame(0, $this->loginHandOver->purgeExpired());
    }

    public function testInvalidPurgeProbabilityThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new LoginHandOver(directory: $this->directory, purgeProbabilityPercent: 101);
    }
}
