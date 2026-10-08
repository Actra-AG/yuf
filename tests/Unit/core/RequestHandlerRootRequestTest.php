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
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * The route that a request of "/" is redirected to: the preferred language of the session, else the first browser
 * language with a default route, else the first default route.
 */
final class RequestHandlerRootRequestTest extends TestCase
{
    private Language $german;
    private Language $english;
    private Language $french;
    private Route $germanRoute;
    private Route $englishRoute;

    #[Override]
    protected function setUp(): void
    {
        $this->german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $this->english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $this->french = new Language(code: 'fr', locale: 'fr_CH.UTF-8');
        $this->germanRoute = $this->createRoute(path: '/de/', language: $this->german);
        $this->englishRoute = $this->createRoute(path: '/en/', language: $this->english);
    }

    private function createRoute(string $path, Language $language, bool $isDefaultForLanguage = true): Route
    {
        return new Route(
            path: $path,
            viewDirectory: '/tmp/views/',
            defaultFileName: 'index.html',
            isDefaultForLanguage: $isDefaultForLanguage,
            language: $language,
        );
    }

    /**
     * @param list<string> $browserLanguages
     */
    private function createHandler(
        array $browserLanguages = [],
        ?string $preferredLanguage = null,
        ?RouteCollection $routeCollection = null,
    ): RequestHandler {
        $storage = new ArraySessionStorage();
        if ($preferredLanguage !== null) {
            $storage->set(key: 'yuf', value: ['handler' => ['preferredLanguage' => $preferredLanguage]]);
        }

        $headers = $browserLanguages === []
            ? []
            : ['Accept-Language' => implode(separator: ',', array: $browserLanguages)];

        return new RequestHandler(
            httpRequest: HttpRequestFactory::create(headers: $headers),
            routeCollection: $routeCollection ?? new RouteCollection(routes: [$this->germanRoute, $this->englishRoute]),
            availableLanguages: new LanguageCollection(languages: [$this->german, $this->english, $this->french]),
            allowedDomains: ['example.com'],
            session: new Session(storage: $storage),
        );
    }

    public function testPreferredLanguageOfTheSessionComesFirst(): void
    {
        $handler = $this->createHandler(browserLanguages: ['de'], preferredLanguage: 'en');

        $this->assertSame($this->englishRoute, $handler->findRouteForRootRequest());
    }

    public function testBrowserLanguageIsUsedWithoutPreferredLanguage(): void
    {
        $handler = $this->createHandler(browserLanguages: ['en']);

        $this->assertSame($this->englishRoute, $handler->findRouteForRootRequest());
    }

    public function testFirstBrowserLanguageWithADefaultRouteWins(): void
    {
        $handler = $this->createHandler(browserLanguages: ['fr', 'en', 'de']);

        $this->assertSame($this->englishRoute, $handler->findRouteForRootRequest());
    }

    public function testPreferredLanguageWithoutDefaultRouteFallsBackToTheBrowserLanguage(): void
    {
        $handler = $this->createHandler(browserLanguages: ['de'], preferredLanguage: 'fr');

        $this->assertSame($this->germanRoute, $handler->findRouteForRootRequest());
    }

    public function testFirstDefaultRouteIsUsedWithoutMatchingLanguage(): void
    {
        $handler = $this->createHandler(browserLanguages: ['fr', 'it']);

        $this->assertSame($this->germanRoute, $handler->findRouteForRootRequest());
    }

    public function testFirstDefaultRouteIsUsedWithoutBrowserLanguages(): void
    {
        $this->assertSame($this->germanRoute, $this->createHandler()->findRouteForRootRequest());
    }

    public function testRouteThatIsNoDefaultOfItsLanguageIsNotARedirectTarget(): void
    {
        $handler = $this->createHandler(
            browserLanguages: ['en'],
            routeCollection: new RouteCollection(routes: [
                $this->createRoute(path: '/en/', language: $this->english, isDefaultForLanguage: false),
                $this->germanRoute,
            ]),
        );

        $this->assertSame($this->germanRoute, $handler->findRouteForRootRequest());
    }

    public function testNoDefaultRouteThrows(): void
    {
        $handler = $this->createHandler(
            routeCollection: new RouteCollection(routes: [
                $this->createRoute(path: '/en/', language: $this->english, isDefaultForLanguage: false),
            ]),
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs(
            'The request of "/" has no route to be redirected to: set isDefaultForLanguage: true on a route with an'
            . ' available language.',
        );
        $handler->findRouteForRootRequest();
    }
}
