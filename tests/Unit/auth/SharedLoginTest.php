<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\auth;

use actra\yuf\auth\SharedLogin;
use actra\yuf\auth\SharedLoginSite;
use actra\yuf\auth\SharedLoginStep;
use actra\yuf\auth\SharedLoginUser;
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

final class SharedLoginTest extends TestCase
{
    private const array ALLOWED_HOSTS = ['www.site-a.example', 'www.example.net'];
    private const string SITE = 'www.site-a.example';
    private const string LOGIN_HOST = 'login.example.net';
    private const string BROWSER_COOKIE = '__Host-yuf-login-browser';
    private const string OTHER_BROWSER = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string LOGIN_BROWSER = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    private TemplateWorkDirectory $workDirectory;
    private string $directory;
    private AdjustableClock $clock;
    private RecordingSsoNonceCookieWriter $cookieWriter;
    private SharedLogin $sharedLogin;
    private SharedLoginSite $site;
    private SharedLoginUser $user;

    #[Override]
    protected function setUp(): void
    {
        $this->workDirectory = new TemplateWorkDirectory();
        $this->directory = $this->workDirectory->cacheDirectory . 'hand-over';
        $this->clock = new AdjustableClock(now: new DateTimeImmutable(datetime: '2026-11-08 12:00:00 UTC'));
        $this->cookieWriter = new RecordingSsoNonceCookieWriter();
        $this->sharedLogin = new SharedLogin(
            directory: $this->directory,
            loginLifetimeInSeconds: 3600,
            clock: $this->clock,
            cookieWriter: $this->cookieWriter,
            purgeProbabilityPercent: 0,
        );
        $this->site = new SharedLoginSite(
            loginPath: '/fr/login.html',
            handOverPath: '/login-hand-over.html',
            loginHostUrl: 'https://login.example.net/login-hand-over.html',
        );
        $this->user = new SharedLoginUser(subject: '42', sharedLoginId: SharedLogin::createSharedLoginId());
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->workDirectory->cleanUp();
    }

    public function testCheckReturnsTheLoginOfTheLoginHost(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertSame('/fr/page.html?a=1', $step->redirectUrl);
        $this->assertEquals($this->user, $step->logIn);
        $this->assertNull($step->failure);
    }

    public function testCheckWithoutLoginOnTheLoginHostReturnsToTheLoginPageOfTheSite(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: null);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertSame('/fr/login.html?returnTo=%2Ffr%2Fpage.html%3Fa%3D1&loginChecked=1', $step->redirectUrl);
        $this->assertNull($step->logIn);
        $this->assertNull($step->failure);
        // Nothing is remembered: only the browser cookie of the check
        $this->assertSame(
            [SharedLoginTest::BROWSER_COOKIE],
            array_values(array: array_unique(array: array_column(array: $this->cookieWriter->setCookies, column_key: 'name'))),
        );
    }

    public function testLoginPageOfTheAnswerMakesNoHop(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: null);
        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        // Also for browsers without cookies: the URL carries the mark (no loop)
        $this->assertTrue($this->sharedLogin->isChecked(httpRequest: $this->siteRequest(url: $step->redirectUrl)));
    }

    public function testEveryOtherRequestChecksAgain(): void
    {
        $this->assertFalse($this->sharedLogin->isChecked(httpRequest: $this->siteRequest(url: '/fr/login.html')));
        $this->assertFalse($this->sharedLogin->isChecked(
            httpRequest: $this->siteRequest(url: '/fr/login.html?loginChecked=0'),
        ));
        // The cookie of v8.13.0 to v8.14.1 is ignored
        $this->assertFalse($this->sharedLogin->isChecked(
            httpRequest: HttpRequestFactory::create(
                host: SharedLoginTest::SITE,
                uri: '/fr/login.html',
                cookies: ['__Host-yuf-login-checked' => '1'],
            ),
        ));
    }

    public function testLoginOnAnotherSiteAfterACheckWithoutLoginIsFoundAtTheNextCheck(): void
    {
        // Site A: the check finds no login, the visitor sees the login page of site A
        [$answerUrl, $siteBrowser] = $this->check(user: null);
        $loginPage = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );
        $this->assertNull($loginPage->logIn);

        // The visitor logs in on another site, which pushes the login to the login host. Back on site A, the next GET
        // of its login page (or of a page that needs a login) has no mark and checks again.
        $this->assertFalse($this->sharedLogin->isChecked(httpRequest: $this->siteRequest(url: '/fr/login.html')));
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user);
        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertEquals($this->user, $step->logIn);
        $this->assertSame('/fr/page.html?a=1', $step->redirectUrl);
    }

    public function testCheckUrlCarriesOnlyARandomId(): void
    {
        $url = $this->sharedLogin->createCheckUrl(
            httpRequest: $this->siteRequest(url: '/fr/page.html'),
            site: $this->site,
            returnPath: '/fr/page.html',
        );

        $this->assertMatchesRegularExpression(
            '#^https://login\.example\.net/login-hand-over\.html\?loginCheck=[0-9a-f]{64}$#D',
            $url,
        );
        $this->assertSame(SharedLoginTest::BROWSER_COOKIE, array_first(array: $this->cookieWriter->setCookies)['name'] ?? null);
        foreach ($this->listFiles() as $file) {
            $this->assertMatchesRegularExpression('#/check-[0-9a-f]{64}\.json$#D', $file);
            $this->assertStringNotContainsString(
                (string) $this->queryValue(url: $url, name: 'loginCheck'),
                (string) file_get_contents(filename: $file),
            );
            $this->assertSame(0o600, fileperms(filename: $file) & 0o777);
        }
    }

    public function testReturnPathThatIsNoLocalPathReturnsToTheRoot(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user, returnPath: '//evil.example.com/');

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertSame('/', $step->redirectUrl);
    }

    public function testHostWithPortCannotStartACheck(): void
    {
        $this->expectException(LogicException::class);

        $this->sharedLogin->createCheckUrl(
            httpRequest: HttpRequestFactory::create(host: 'site.example.com:8443'),
            site: $this->site,
            returnPath: '/',
        );
    }

    public function testCheckOfAHostThatIsNotAllowedIsRefused(): void
    {
        $checkUrl = $this->sharedLogin->createCheckUrl(
            httpRequest: HttpRequestFactory::create(host: 'evil.example.com'),
            site: $this->site,
            returnPath: '/',
        );

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: $checkUrl, user: $this->user);
    }

    public function testExpiredCheckIsRefused(): void
    {
        $checkUrl = $this->createCheckUrl();
        $this->clock->advanceSeconds(seconds: 60);

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: $checkUrl, user: $this->user);
    }

    public function testCheckIsUsedOnce(): void
    {
        $checkUrl = $this->createCheckUrl();
        $this->receiveOnLoginHost(url: $checkUrl, user: $this->user);

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: $checkUrl, user: $this->user);
    }

    public function testAnswerIsUsedOnce(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user);
        $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertNull($step->logIn);
        $this->assertSame('/fr/login.html?loginChecked=1', $step->redirectUrl);
        $this->assertSame('Unknown, used or expired answer', $step->failure);
    }

    public function testExpiredAnswerIsRefused(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user);
        $this->clock->advanceSeconds(seconds: 60);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertNull($step->logIn);
        $this->assertNotNull($step->failure);
    }

    public function testAnswerInAnotherBrowserIsRefused(): void
    {
        // Login CSRF: the check of an attacker's browser, answered in the visitor's browser on the login host
        [$answerUrl] = $this->check(user: $this->user);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: SharedLoginTest::OTHER_BROWSER),
            site: $this->site,
        );

        $this->assertNull($step->logIn);
        $this->assertSame('/fr/login.html?returnTo=%2Ffr%2Fpage.html%3Fa%3D1&loginChecked=1', $step->redirectUrl);
        $this->assertSame('Answer of another browser', $step->failure);
    }

    public function testAnswerOnAnotherHostIsRefused(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: HttpRequestFactory::create(
                host: 'www.example.net',
                uri: (string) parse_url(url: $answerUrl, component: PHP_URL_PATH),
                cookies: [SharedLoginTest::BROWSER_COOKIE => $siteBrowser],
                queryParameters: ['loginAnswer' => (string) $this->queryValue(url: $answerUrl, name: 'loginAnswer')],
            ),
            site: $this->site,
        );

        $this->assertNull($step->logIn);
    }

    public function testHandOverPageWithoutStepLeadsToTheLoginPage(): void
    {
        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: '/login-hand-over.html'),
            site: $this->site,
        );

        $this->assertSame('/fr/login.html?loginChecked=1', $step->redirectUrl);
        $this->assertNull($step->logIn);
    }

    public function testLoginHostWithoutStepThrows(): void
    {
        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: 'https://login.example.net/login-hand-over.html', user: null);
    }

    public function testPushLogsTheSameUserInOnTheLoginHostAndReturnsToTheSite(): void
    {
        $siteBrowser = $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);

        $bound = $this->receiveOnLoginHost(url: $pushUrl, user: null);
        $this->assertNull($bound->logIn);
        $this->assertMatchesRegularExpression(
            '#^https://www\.site-a\.example/login-hand-over\.html\?loginPush=[0-9a-f]{64}$#D',
            $bound->redirectUrl,
        );
        $this->assertNotSame($this->queryValue(url: $pushUrl, name: 'loginPush'), $this->queryValue(
            url: $bound->redirectUrl,
            name: 'loginPush',
        ));
        $confirmed = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $bound->redirectUrl, browser: $siteBrowser),
            site: $this->site,
        );
        $this->assertNull($confirmed->logIn);
        $this->assertNull($confirmed->failure);
        $this->assertMatchesRegularExpression(
            '#^https://login\.example\.net/login-hand-over\.html\?loginPush=[0-9a-f]{64}$#D',
            $confirmed->redirectUrl,
        );
        $done = $this->receiveOnLoginHost(url: $confirmed->redirectUrl, user: null);

        $this->assertEquals($this->user, $done->logIn);
        $this->assertSame('https://www.site-a.example/fr/page.html', $done->redirectUrl);
        $this->assertSame([], $this->listFiles());
    }

    public function testPushIdKnownToAnAttackerCannotConfirmTheStep(): void
    {
        // The attacker starts a push of his account and lets the visitor's browser run the first step without
        // following its redirect: the ID of the next step is new, so the attacker cannot confirm it himself
        $attackerBrowser = $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);
        $this->receiveOnLoginHost(url: $pushUrl, user: null);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(
                url: '/login-hand-over.html?loginPush=' . $this->queryValue(url: $pushUrl, name: 'loginPush'),
                browser: $attackerBrowser,
            ),
            site: $this->site,
        );

        $this->assertSame('Unknown, used or expired push', $step->failure);
    }

    public function testPushConfirmedInAnotherBrowserOnTheSiteIsRefused(): void
    {
        // Login CSRF: the push of an attacker's browser, bound in the visitor's browser on the login host
        $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);
        $bound = $this->receiveOnLoginHost(url: $pushUrl, user: null);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $bound->redirectUrl, browser: SharedLoginTest::OTHER_BROWSER),
            site: $this->site,
        );

        $this->assertSame('Push of another browser', $step->failure);
        $this->assertSame('/fr/login.html?returnTo=%2Ffr%2Fpage.html&loginChecked=1', $step->redirectUrl);
    }

    public function testPushCompletedInAnotherBrowserOnTheLoginHostIsRefused(): void
    {
        $siteBrowser = $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);
        $bound = $this->receiveOnLoginHost(url: $pushUrl, user: null);
        $confirmed = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $bound->redirectUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(
            url: $confirmed->redirectUrl,
            user: null,
            browser: SharedLoginTest::OTHER_BROWSER,
        );
    }

    public function testPushStepsAreUsedOnce(): void
    {
        $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);
        $this->receiveOnLoginHost(url: $pushUrl, user: null);

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: $pushUrl, user: null);
    }

    public function testExpiredPushIsRefused(): void
    {
        $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);
        $this->clock->advanceSeconds(seconds: 60);

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: $pushUrl, user: null);
    }

    public function testPushOfAHostThatIsNotAllowedIsRefused(): void
    {
        $pushUrl = $this->sharedLogin->createPushUrl(
            httpRequest: HttpRequestFactory::create(host: 'evil.example.com'),
            site: $this->site,
            user: $this->user,
            returnPath: '/',
        );

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: $pushUrl, user: null);
    }

    public function testPushThatIsNotConfirmedBySiteCannotLogIn(): void
    {
        $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);
        $bound = $this->receiveOnLoginHost(url: $pushUrl, user: null);

        // The ID of the second step at the login host instead of at the site
        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(
            url: 'https://login.example.net/login-hand-over.html?loginPush='
                . $this->queryValue(url: $bound->redirectUrl, name: 'loginPush'),
            user: null,
        );
    }

    public function testEndedLoginIsEndedOnEveryHost(): void
    {
        $this->assertFalse($this->sharedLogin->isEnded(sharedLoginId: $this->user->sharedLoginId));

        $this->sharedLogin->end(sharedLoginId: $this->user->sharedLoginId);

        $this->assertTrue($this->sharedLogin->isEnded(sharedLoginId: $this->user->sharedLoginId));
        $this->assertFalse($this->sharedLogin->isEnded(sharedLoginId: SharedLogin::createSharedLoginId()));
        $this->clock->advanceSeconds(seconds: 3600);
        $this->assertFalse($this->sharedLogin->isEnded(sharedLoginId: $this->user->sharedLoginId));
    }

    public function testInvalidSharedLoginIdCountsAsEnded(): void
    {
        $this->assertTrue($this->sharedLogin->isEnded(sharedLoginId: 'abc'));
    }

    public function testEndingAnInvalidSharedLoginIdThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sharedLogin->end(sharedLoginId: 'abc');
    }

    public function testCheckAfterLogoutAnswersWithoutLogin(): void
    {
        $this->sharedLogin->end(sharedLoginId: $this->user->sharedLoginId);
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertNull($step->logIn);
        $this->assertNull($step->failure);
    }

    public function testLoginEndedDuringTheCheckIsNotHandedOver(): void
    {
        [$answerUrl, $siteBrowser] = $this->check(user: $this->user);
        $this->sharedLogin->end(sharedLoginId: $this->user->sharedLoginId);

        $step = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $answerUrl, browser: $siteBrowser),
            site: $this->site,
        );

        $this->assertNull($step->logIn);
    }

    public function testPushOfAnEndedLoginIsRefused(): void
    {
        $siteBrowser = $this->startPushAndGetSiteBrowser(pushUrl: $pushUrl);
        $bound = $this->receiveOnLoginHost(url: $pushUrl, user: null);
        $confirmed = $this->sharedLogin->receiveOnSite(
            httpRequest: $this->siteRequest(url: $bound->redirectUrl, browser: $siteBrowser),
            site: $this->site,
        );
        $this->sharedLogin->end(sharedLoginId: $this->user->sharedLoginId);

        $this->expectException(UnauthorizedException::class);

        $this->receiveOnLoginHost(url: $confirmed->redirectUrl, user: null);
    }

    public function testInvalidLoginLifetimeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SharedLogin(directory: $this->directory, loginLifetimeInSeconds: 0);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidSites(): iterable
    {
        yield 'external login path' => ['//evil.example.com/', '/hand-over.html', 'https://login.example.net/h.html'];
        yield 'hand-over path with query' => ['/login.html', '/hand-over.html?a=1', 'https://login.example.net/h.html'];
        yield 'http login host' => ['/login.html', '/hand-over.html', 'http://login.example.net/h.html'];
        yield 'login host with query' => ['/login.html', '/hand-over.html', 'https://login.example.net/h.html?a=1'];
        yield 'login host with port' => ['/login.html', '/hand-over.html', 'https://login.example.net:8443/h.html'];
    }

    #[DataProvider('invalidSites')]
    public function testInvalidSiteThrows(string $loginPath, string $handOverPath, string $loginHostUrl): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SharedLoginSite(loginPath: $loginPath, handOverPath: $handOverPath, loginHostUrl: $loginHostUrl);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidUsers(): iterable
    {
        yield 'empty subject' => ['', str_repeat(string: 'a', times: 64)];
        yield 'short shared login ID' => ['42', 'abc'];
    }

    #[DataProvider('invalidUsers')]
    public function testInvalidUserThrows(string $subject, string $sharedLoginId): void
    {
        $this->expectException(InvalidArgumentException::class);

        new SharedLoginUser(subject: $subject, sharedLoginId: $sharedLoginId);
    }

    /**
     * Runs a check from the site through the login host; returns the URL of the answer and the browser of the site.
     *
     * @return array{string, string}
     */
    private function check(?SharedLoginUser $user, string $returnPath = '/fr/page.html?a=1'): array
    {
        $checkUrl = $this->createCheckUrl(returnPath: $returnPath);
        $siteBrowser = $this->lastBrowserCookie();
        $step = $this->receiveOnLoginHost(url: $checkUrl, user: $user);
        $this->assertNull($step->logIn);
        $this->assertMatchesRegularExpression(
            '#^https://www\.site-a\.example/login-hand-over\.html\?loginAnswer=[0-9a-f]{64}$#D',
            $step->redirectUrl,
        );

        return [$step->redirectUrl, $siteBrowser];
    }

    private function createCheckUrl(string $returnPath = '/fr/page.html?a=1'): string
    {
        return $this->sharedLogin->createCheckUrl(
            httpRequest: $this->siteRequest(url: $returnPath),
            site: $this->site,
            returnPath: $returnPath,
        );
    }

    /**
     * Starts a push on the site after the login of the user; returns the browser of the site.
     *
     * @param-out string $pushUrl
     */
    private function startPushAndGetSiteBrowser(?string &$pushUrl): string
    {
        $pushUrl = $this->sharedLogin->createPushUrl(
            httpRequest: $this->siteRequest(url: '/fr/login.html'),
            site: $this->site,
            user: $this->user,
            returnPath: '/fr/page.html',
        );
        $this->assertMatchesRegularExpression(
            '#^https://login\.example\.net/login-hand-over\.html\?loginPush=[0-9a-f]{64}$#D',
            $pushUrl,
        );

        return $this->lastBrowserCookie();
    }

    private function receiveOnLoginHost(
        string $url,
        ?SharedLoginUser $user,
        string $browser = SharedLoginTest::LOGIN_BROWSER,
    ): SharedLoginStep {
        return $this->sharedLogin->receiveOnLoginHost(
            httpRequest: $this->request(host: SharedLoginTest::LOGIN_HOST, url: $url, browser: $browser),
            allowedHosts: SharedLoginTest::ALLOWED_HOSTS,
            user: $user,
        );
    }

    private function siteRequest(string $url, ?string $browser = null): HttpRequest
    {
        return $this->request(host: SharedLoginTest::SITE, url: $url, browser: $browser);
    }

    private function request(string $host, string $url, ?string $browser): HttpRequest
    {
        $query = (string) parse_url(url: $url, component: PHP_URL_QUERY);
        parse_str(string: $query, result: $queryParameters);
        $path = (string) parse_url(url: $url, component: PHP_URL_PATH);

        return HttpRequestFactory::create(
            host: $host,
            uri: $path . ($query === '' ? '' : '?' . $query),
            queryString: $query,
            cookies: $browser === null ? [] : [SharedLoginTest::BROWSER_COOKIE => $browser],
            queryParameters: $queryParameters,
        );
    }

    private function queryValue(string $url, string $name): ?string
    {
        parse_str(string: (string) parse_url(url: $url, component: PHP_URL_QUERY), result: $parameters);
        $value = $parameters[$name] ?? null;

        return is_string(value: $value) ? $value : null;
    }

    private function lastBrowserCookie(): string
    {
        $cookies = array_filter(
            array: $this->cookieWriter->setCookies,
            callback: static fn(array $cookie): bool => $cookie['name'] === SharedLoginTest::BROWSER_COOKIE,
        );
        $cookie = array_last(array: $cookies);
        $this->assertNotNull($cookie);

        return $cookie['value'];
    }

    /**
     * @return list<string>
     */
    private function listFiles(): array
    {
        $files = glob(pattern: $this->directory . '/*');

        return $files === false ? [] : $files;
    }
}
