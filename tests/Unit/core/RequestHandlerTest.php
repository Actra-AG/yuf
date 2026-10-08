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
use actra\yuf\exception\NotFoundException;
use actra\yuf\tests\Double\core\HttpRequestFactory;
use LogicException;
use PHPUnit\Framework\TestCase;

/**
 * Not covered: redirects of "/" (they exit) and the preferred language in the session.
 */
final class RequestHandlerTest extends TestCase
{
    private function createRoute(string $path, ?Language $language = null, bool $isDefaultForLanguage = false): Route
    {
        return new Route(
            path: $path,
            viewDirectory: '/tmp/views/',
            defaultFileName: 'index.html',
            isDefaultForLanguage: $isDefaultForLanguage,
            language: $language,
        );
    }

    private function createRequestHandler(
        string $requestUri,
        RouteCollection $routeCollection,
        LanguageCollection $availableLanguages = new LanguageCollection(),
        ?string $allowedDomain = null,
        string $host = 'example.com',
    ): RequestHandler {
        return new RequestHandler(
            httpRequest: HttpRequestFactory::create(host: $host, uri: $requestUri),
            routeCollection: $routeCollection,
            availableLanguages: $availableLanguages,
            allowedDomains: [$allowedDomain ?? $host],
        );
    }

    public function testConstructorSetsTheFirstAvailableLanguageAndTheDefaultRoutes(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $germanRoute = $this->createRoute(path: '/de/', language: $german, isDefaultForLanguage: true);
        $englishRoute = $this->createRoute(path: '/en/', language: $english, isDefaultForLanguage: true);
        $otherRoute = $this->createRoute(path: '/other/', language: $english);

        $handler = $this->createRequestHandler(
            requestUri: '/nope/x.html',
            routeCollection: new RouteCollection(routes: [$germanRoute, $englishRoute, $otherRoute]),
            availableLanguages: new LanguageCollection(languages: [$german, $english]),
        );

        $this->assertSame($german, $handler->language);
        $this->assertSame('x.html', $handler->fileName);
        $this->assertSame(['', 'nope', 'x.html'], $handler->pathParts);
        $this->assertSame([$germanRoute, $englishRoute], $handler->defaultRoutesByLanguage?->routes);
        $this->assertSame('/de/', $handler->getLanguageRoot());
    }

    public function testConstructorWithoutLanguages(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $this->assertNull($handler->language);
        $this->assertSame('/', $handler->getLanguageRoot());
    }

    public function testResolveRouteSplitsTheFileName(): void
    {
        $route = $this->createRoute(path: '/');
        $handler = $this->createRequestHandler(
            requestUri: '/detail-12-a__DASH__b.html?x=1',
            routeCollection: new RouteCollection(routes: [$route]),
        );

        $handler->resolveRoute();

        $this->assertSame($route, $handler->route);
        $this->assertSame('detail-12-a__DASH__b.html', $handler->fileName);
        $this->assertSame('detail', $handler->fileTitle);
        $this->assertSame('html', $handler->fileExtension);
        $this->assertSame(['detail', '12', 'a-b'], $handler->pathVars);
        $this->assertSame('12', $handler->getPathVar(nr: 1));
        $this->assertNull($handler->getPathVar(nr: 3));
    }

    public function testResolveRouteUsesTheDefaultFileNameAndTheRouteLanguage(): void
    {
        $german = new Language(code: 'de', locale: 'de_CH.UTF-8');
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $handler = $this->createRequestHandler(
            requestUri: '/en/',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/en/', language: $english)]),
            availableLanguages: new LanguageCollection(languages: [$german, $english]),
        );

        $handler->resolveRoute();

        $this->assertSame($english, $handler->language);
        $this->assertSame('index.html', $handler->fileName);
        $this->assertSame('index', $handler->fileTitle);
    }

    public function testResolveRouteThrowsForAnUnknownRoute(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/nope/x.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $this->expectException(NotFoundException::class);
        $handler->resolveRoute();
    }

    public function testResolveRouteThrowsForADomainThatIsNotAllowed(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
            allowedDomain: 'other.example',
        );

        $this->expectException(NotFoundException::class);
        $handler->resolveRoute();
    }

    public function testResolveRouteThrowsForDoubleSlashes(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/a//index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $this->expectException(NotFoundException::class);
        $handler->resolveRoute();
    }

    public function testResolveRouteTwiceThrows(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );
        $handler->resolveRoute();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageIs('The route is already resolved');
        $handler->resolveRoute();
    }

    public function testTheLanguageAndTheFileNameSurviveAnUnknownRoute(): void
    {
        $english = new Language(code: 'en', locale: 'en_US.UTF-8');
        $handler = $this->createRequestHandler(
            requestUri: '/nope/x.html',
            routeCollection: new RouteCollection(
                routes: [$this->createRoute(path: '/en/', language: $english, isDefaultForLanguage: true)],
            ),
            availableLanguages: new LanguageCollection(languages: [$english]),
        );
        try {
            $handler->resolveRoute();
        } catch (NotFoundException) {
        }

        $this->assertSame($english, $handler->language);
        $this->assertSame('x.html', $handler->fileName);
        $this->assertSame('/en/', $handler->getLanguageRoot());
    }

    public function testTheHostIsComparedWithThePortTheClientSent(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/index.html',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
            allowedDomain: 'example.com',
            host: 'example.com:8443',
        );

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessageIs('example.com:8443 is not set as allowed domain in your environment settings.');
        $handler->resolveRoute();
    }

    public function testTheQueryStringIsNotPartOfThePath(): void
    {
        $handler = $this->createRequestHandler(
            requestUri: '/detail-5.html?a=//b',
            routeCollection: new RouteCollection(routes: [$this->createRoute(path: '/')]),
        );

        $handler->resolveRoute();

        $this->assertSame('detail-5.html', $handler->fileName);
    }
}
