<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\session\AbstractSessionHandler;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use actra\yuf\tests\Double\session\NonStartingSessionHandler;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Characterization of the session behaviour before the redesign (docs/session/plan.md, step 1): the preferred
 * language of the user, written by `RequestHandler::resolveRoute()` and kept by the session handler. The behaviour
 * tests only use `seedPreferredLanguage()` and `storedPreferredLanguage()`; the key is pinned in
 * `testStorageLayout…()` only (it may change in step 2).
 *
 * Not covered: the redirect of "/" to the route of the preferred language (`HttpResponse::redirectAndExit()` exits).
 * The registered session handler is a stand-in that does not start a session (`NonStartingSessionHandler`, registered
 * through reflection; removed in step 2).
 */
final class RequestHandlerPreferredLanguageTest extends TestCase
{
    private Language $german;
    private Language $english;

    #[Override]
    protected function setUp(): void
    {
        $this->german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $this->english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $_SESSION = [];
        NonStartingSessionHandler::install();
    }

    #[Override]
    protected function tearDown(): void
    {
        NonStartingSessionHandler::uninstall();
        unset($_SESSION); // Sessions are disabled in the CLI
    }

    private function seedPreferredLanguage(string $code): void
    {
        $_SESSION['preferredLanguage'] = $code;
    }

    private function storedPreferredLanguage(): ?string
    {
        $code = $_SESSION['preferredLanguage'] ?? null;

        return is_string(value: $code) ? $code : null;
    }

    private function resolve(string $uri, ?Language $routeLanguage, LanguageCollection $available): RequestHandler
    {
        $handler = new RequestHandler(
            httpRequest: HttpRequestFactory::create(uri: $uri),
            routeCollection: new RouteCollection(
                routes: [
                    new Route(
                        path: '/en/',
                        viewDirectory: '/tmp/views/',
                        defaultFileName: 'index.html',
                        isDefaultForLanguage: false,
                        language: $routeLanguage,
                    ),
                ],
            ),
            availableLanguages: $available,
            allowedDomains: ['example.com'],
        );
        $handler->resolveRoute();

        return $handler;
    }

    private function bothLanguages(): LanguageCollection
    {
        return new LanguageCollection(languages: [$this->german, $this->english]);
    }

    public function testStorageLayoutIsTheTopLevelKeyPreferredLanguageWithTheLanguageCode(): void
    {
        $this->resolve(uri: '/en/', routeLanguage: $this->english, available: $this->bothLanguages());

        $this->assertSame(['preferredLanguage' => 'en'], $_SESSION);
    }

    public function testLanguageOfTheRouteIsRememberedAsPreferredLanguage(): void
    {
        $this->resolve(uri: '/en/', routeLanguage: $this->english, available: $this->bothLanguages());

        $this->assertSame('en', $this->storedPreferredLanguage());
    }

    public function testPreferredLanguageIsReplacedWhenTheLanguageOfTheRouteDiffers(): void
    {
        $this->seedPreferredLanguage(code: 'de');

        $this->resolve(uri: '/en/', routeLanguage: $this->english, available: $this->bothLanguages());

        $this->assertSame('en', $this->storedPreferredLanguage());
    }

    public function testPreferredLanguageStaysWhenTheLanguageOfTheRouteIsTheSame(): void
    {
        $this->seedPreferredLanguage(code: 'en');

        $this->resolve(uri: '/en/', routeLanguage: $this->english, available: $this->bothLanguages());

        $this->assertSame('en', $this->storedPreferredLanguage());
    }

    /**
     * Not a feature but today's behaviour: a route without language falls back to the first available language,
     * which then overwrites the preferred language of the user (findings of docs/session/plan.md, step 1).
     */
    public function testRouteWithoutLanguageWritesTheFirstAvailableLanguage(): void
    {
        $this->seedPreferredLanguage(code: 'en');

        $this->resolve(uri: '/en/', routeLanguage: null, available: $this->bothLanguages());

        $this->assertSame('de', $this->storedPreferredLanguage());
    }

    public function testWithoutAvailableLanguagesNothingIsWritten(): void
    {
        $this->resolve(uri: '/en/', routeLanguage: null, available: new LanguageCollection());

        $this->assertSame([], $_SESSION);
    }

    public function testLanguageOfTheRouteThatIsNotAvailableThrows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The preferred language en is not available');

        $this->resolve(
            uri: '/en/',
            routeLanguage: $this->english,
            available: new LanguageCollection(languages: [$this->german]),
        );
    }

    public function testNothingIsWrittenWithoutSession(): void
    {
        unset($_SESSION);

        $this->resolve(uri: '/en/', routeLanguage: $this->english, available: $this->bothLanguages());

        $this->assertFalse(AbstractSessionHandler::enabled());
    }

    public function testSessionHandlerReadsThePreferredLanguage(): void
    {
        $this->seedPreferredLanguage(code: 'en');

        $this->assertSame('en', AbstractSessionHandler::getSessionHandler()->getPreferredLanguageCode());
    }

    public function testSessionHandlerWithoutPreferredLanguageReturnsNull(): void
    {
        $this->assertNull(AbstractSessionHandler::getSessionHandler()->getPreferredLanguageCode());
    }

    public function testSessionHandlerIgnoresAPreferredLanguageThatIsNoString(): void
    {
        $_SESSION['preferredLanguage'] = ['en'];

        $this->assertNull(AbstractSessionHandler::getSessionHandler()->getPreferredLanguageCode());
    }

    public function testSessionHandlerWritesThePreferredLanguage(): void
    {
        AbstractSessionHandler::getSessionHandler()->setPreferredLanguage(language: $this->english);

        $this->assertSame('en', $this->storedPreferredLanguage());
        $this->assertSame('en', AbstractSessionHandler::getSessionHandler()->getPreferredLanguageCode());
    }

    public function testPreferredLanguageSurvivesTheClearingOfTheUserData(): void
    {
        $this->seedPreferredLanguage(code: 'en');
        $_SESSION['other'] = 'data';

        AbstractSessionHandler::clearUserData();

        $this->assertSame('en', $this->storedPreferredLanguage());
        $this->assertArrayNotHasKey('other', $_SESSION);
    }
}
