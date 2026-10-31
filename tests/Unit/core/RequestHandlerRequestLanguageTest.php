<?php

/**
 * @copyright Actra AG - https://www.actra.ch
 * @license   MIT
 */

declare(strict_types=1);

namespace actra\yuf\tests\Unit\core;

use actra\yuf\core\Language;
use actra\yuf\core\LanguageCollection;
use actra\yuf\core\RecordingResponseSender;
use actra\yuf\core\RequestHandler;
use actra\yuf\core\RequestLanguageProvider;
use actra\yuf\core\ResponseSender;
use actra\yuf\core\Route;
use actra\yuf\core\RouteCollection;
use actra\yuf\session\ArraySessionStorage;
use actra\yuf\session\Session;
use actra\yuf\tests\Double\core\FixedRequestLanguageProvider;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use LogicException;
use Override;
use PHPUnit\Framework\TestCase;

/**
 * Routes with `usesRequestLanguage`: one path for all languages, the language of each request from
 * `RequestLanguageResolver` (the order itself: `RequestLanguageResolverTest`).
 */
final class RequestHandlerRequestLanguageTest extends TestCase
{
    private Language $german;
    private Language $english;

    #[Override]
    protected function setUp(): void
    {
        $this->german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $this->english = new Language(code: 'en', locale: 'en_US.UTF-8');
    }

    private function createRequestLanguageRoute(string $path = '/', bool $isDefaultForLanguage = true): Route
    {
        return new Route(
            path: $path,
            viewDirectory: '/tmp/views/',
            defaultFileName: 'index.html',
            isDefaultForLanguage: $isDefaultForLanguage,
            usesRequestLanguage: true,
        );
    }

    /**
     * @param list<Route> $routes
     */
    private function createHandler(
        array $routes,
        string $uri = '/',
        string $acceptLanguage = '',
        ?string $preferredLanguage = null,
        ?RequestLanguageProvider $provider = null,
        ResponseSender $responseSender = new RecordingResponseSender(),
    ): RequestHandler {
        $storage = new ArraySessionStorage();
        if ($preferredLanguage !== null) {
            $storage->set(key: 'yuf', value: ['handler' => ['preferredLanguage' => $preferredLanguage]]);
        }

        return new RequestHandler(
            httpRequest: HttpRequestFactory::create(
                uri: $uri,
                headers: $acceptLanguage === '' ? [] : ['Accept-Language' => $acceptLanguage],
            ),
            routeCollection: new RouteCollection(routes: $routes),
            availableLanguages: new LanguageCollection(languages: [$this->german, $this->english]),
            allowedDomains: ['example.com'],
            session: new Session(storage: $storage),
            responseSender: $responseSender,
            requestLanguageProvider: $provider,
        );
    }

    public function testLanguageOfTheProviderIsTheLanguageOfTheRequest(): void
    {
        $handler = $this->createHandler(
            routes: [$this->createRequestLanguageRoute()],
            acceptLanguage: 'de',
            preferredLanguage: 'de',
            provider: new FixedRequestLanguageProvider(languageCode: 'en'),
        );

        $resolvedRoute = $handler->resolveRoute();

        $this->assertSame($this->english, $resolvedRoute->language);
        $this->assertSame($this->english, $handler->language);
    }

    public function testLanguageOfTheSessionIsTheLanguageOfTheRequest(): void
    {
        $resolvedRoute = $this->createHandler(
            routes: [$this->createRequestLanguageRoute()],
            acceptLanguage: 'de',
            preferredLanguage: 'en',
        )->resolveRoute();

        $this->assertSame($this->english, $resolvedRoute->language);
    }

    public function testBrowserLanguageIsTheLanguageOfTheRequest(): void
    {
        $resolvedRoute = $this->createHandler(
            routes: [$this->createRequestLanguageRoute()],
            acceptLanguage: 'en',
        )->resolveRoute();

        $this->assertSame($this->english, $resolvedRoute->language);
    }

    public function testFirstLanguageIsTheLanguageOfTheRequestWithoutAnyOther(): void
    {
        $resolvedRoute = $this->createHandler(routes: [$this->createRequestLanguageRoute()])->resolveRoute();

        $this->assertSame($this->german, $resolvedRoute->language);
    }

    public function testProviderIsNotAskedForARouteWithAFixedLanguage(): void
    {
        $provider = new FixedRequestLanguageProvider(languageCode: 'en');
        $route = new Route(
            path: '/',
            viewDirectory: '/tmp/views/',
            defaultFileName: 'index.html',
            language: $this->german,
        );

        $resolvedRoute = $this->createHandler(routes: [$route], provider: $provider)->resolveRoute();

        $this->assertSame($this->german, $resolvedRoute->language);
        $this->assertSame(0, $provider->calls);
    }

    public function testRequestLanguageIsNotRememberedAsPreferredLanguage(): void
    {
        $storage = new ArraySessionStorage();
        $handler = new RequestHandler(
            httpRequest: HttpRequestFactory::create(headers: ['Accept-Language' => 'en']),
            routeCollection: new RouteCollection(routes: [$this->createRequestLanguageRoute()]),
            availableLanguages: new LanguageCollection(languages: [$this->german, $this->english]),
            allowedDomains: ['example.com'],
            session: new Session(storage: $storage),
        );

        $handler->rememberPreferredLanguage(resolvedRoute: $handler->resolveRoute());

        $this->assertSame([], $storage->all());
    }

    public function testRootIsRedirectedToTheDefaultRouteWithRequestLanguage(): void
    {
        $sentResponse = RecordingResponseSender::capture(
            action: function (ResponseSender $sender): void {
                $this->createHandler(
                    routes: [$this->createRequestLanguageRoute(path: '/backend/')],
                    responseSender: $sender,
                )->resolveRoute();
            },
        );

        $this->assertSame('https://example.com/backend/', $sentResponse->getHeader(key: 'Location'));
    }

    public function testDefaultRouteOfAFixedLanguageComesBeforeTheDefaultRouteWithRequestLanguage(): void
    {
        $germanRoute = new Route(
            path: '/de/',
            viewDirectory: '/tmp/views/',
            defaultFileName: 'index.html',
            isDefaultForLanguage: true,
            language: $this->german,
        );
        $handler = $this->createHandler(
            routes: [$this->createRequestLanguageRoute(path: '/backend/'), $germanRoute],
            acceptLanguage: 'en',
        );

        $this->assertSame($germanRoute, $handler->findRouteForRootRequest());
    }

    public function testDefaultRouteWithRequestLanguageIsTheDefaultRouteOfEveryLanguage(): void
    {
        $route = $this->createRequestLanguageRoute(path: '/backend/');
        $handler = $this->createHandler(routes: [$route], uri: '/backend/', acceptLanguage: 'en');
        $handler->resolveRoute();

        $this->assertSame($route, $handler->findDefaultRouteForLanguage(languageCode: 'en'));
        $this->assertSame($route, $handler->findDefaultRouteForLanguage(languageCode: 'de'));
        $this->assertSame('/backend/', $handler->getLanguageRoot());
    }

    public function testRouteWithRequestLanguageThatIsNoDefaultIsNoRedirectTarget(): void
    {
        $handler = $this->createHandler(routes: [$this->createRequestLanguageRoute(isDefaultForLanguage: false)]);

        $this->assertNull($handler->defaultRequestLanguageRoute);
        $this->assertNull($handler->findDefaultRouteForLanguage(languageCode: 'de'));
    }

    public function testTwoDefaultRoutesWithRequestLanguageThrow(): void
    {
        $this->expectException(LogicException::class);

        $this->createHandler(
            routes: [
                $this->createRequestLanguageRoute(path: '/a/'),
                $this->createRequestLanguageRoute(path: '/b/'),
            ],
        );
    }
}
